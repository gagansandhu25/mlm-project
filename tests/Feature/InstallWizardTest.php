<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\SystemSetting;
use App\Models\Downline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InstallWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The base TestCase marks every test "installed" by default;
        // this suite specifically needs the not-yet-installed state.
        SystemSetting::query()->where('key', 'installed_at')->delete();
        Cache::forget('system_setting:installed_at');
    }

    public function test_a_fresh_app_redirects_every_request_to_the_installer(): void
    {
        $this->get('/')->assertRedirect(route('install'));
        $this->get('/login')->assertRedirect(route('install'));

        // /dashboard sits behind Laravel's 'auth' middleware, which has
        // higher dispatch priority than a plain appended middleware, so an
        // unauthenticated visit bounces to /login first (which then, per
        // the assertion above, correctly bounces on to /install).
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_the_installer_itself_is_reachable_when_not_installed(): void
    {
        $this->get('/install')->assertOk();
    }

    public function test_completing_the_wizard_with_a_unilevel_plan_creates_the_admin_and_marks_installed(): void
    {
        $component = Volt::test('pages.install.wizard')
            ->set('companyName', 'Acme Direct Selling')
            ->set('supportEmail', 'support@acme.test')
            ->set('planType', 'unilevel')
            ->set('adminName', 'Jane Admin')
            ->set('adminEmail', 'jane@acme.test')
            ->set('adminPassword', 'password')
            ->set('adminPassword_confirmation', 'password');

        $component->call('install');

        // The wizard deliberately doesn't log the new admin in itself (see
        // wizard.blade.php's install() for why: this request's session may
        // have started under the `file` driver pre-migration, which the
        // next request won't recognize under `database` sessions) — it
        // sends them to the login page to authenticate fresh instead.
        $component->assertRedirect('/admin/login');
        $this->assertGuest();

        $admin = User::where('email', 'jane@acme.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $admin->role);
        $this->assertTrue(
            Downline::query()->where('ancestor_id', $admin->id)->where('descendant_id', $admin->id)->where('depth', 0)->exists()
        );
        $this->assertNotNull($admin->email_verified_at);

        $this->assertSame('unilevel', SystemSetting::get('active_plan_type'));
        $this->assertSame('Acme Direct Selling', SystemSetting::get('company_name'));
        $this->assertNotNull(SystemSetting::get('installed_at'));

        $this->assertEquals(10, CommissionConfiguration::where('plan_type', 'unilevel')->where('level', 1)->value('percentage'));
        $this->assertSame(10, CommissionConfiguration::where('plan_type', 'unilevel')->count());
    }

    public function test_completing_the_wizard_with_a_binary_plan_writes_a_single_pairing_config(): void
    {
        $component = Volt::test('pages.install.wizard')
            ->set('companyName', 'Acme Direct Selling')
            ->set('supportEmail', 'support@acme.test')
            ->set('planType', 'binary')
            ->set('binaryPairPercentage', 12)
            ->set('adminName', 'Jane Admin')
            ->set('adminEmail', 'jane@acme.test')
            ->set('adminPassword', 'password')
            ->set('adminPassword_confirmation', 'password');

        $component->call('install');

        $this->assertSame('binary', SystemSetting::get('active_plan_type'));
        $this->assertSame('12', SystemSetting::get('binary_pair_percentage'));
        $this->assertSame(1, CommissionConfiguration::where('plan_type', 'binary')->count());
        $this->assertEquals(12, CommissionConfiguration::where('plan_type', 'binary')->value('percentage'));
        $this->assertSame(0, CommissionConfiguration::where('plan_type', 'unilevel')->count());
    }

    public function test_step_validation_blocks_advancing_without_required_fields(): void
    {
        // Validation fails before the DB-connection test/env-write code in
        // next() ever runs, so this doesn't need env-file isolation.
        $component = Volt::test('pages.install.wizard')->set('appName', '');

        $component->call('next');
        $component->assertHasErrors(['appName']);
        $this->assertSame(1, $component->get('step'));
    }

    public function test_step_one_rejects_unreachable_database_credentials(): void
    {
        $this->useScratchEnvFile();

        $component = Volt::test('pages.install.wizard')
            ->set('appName', 'Acme')
            ->set('dbConnection', 'sqlite')
            ->set('dbDatabase', '/this/directory/does/not/exist/db.sqlite');

        $component->call('next');

        $component->assertHasErrors(['dbDatabase']);
        $this->assertSame(1, $component->get('step'));
    }

    public function test_step_one_accepts_valid_database_credentials_writes_env_and_advances(): void
    {
        $envPath = $this->useScratchEnvFile();
        $scratchDb = tempnam(sys_get_temp_dir(), 'mlm_test_db_').'.sqlite';

        $component = Volt::test('pages.install.wizard')
            ->set('appName', 'Acme Platform')
            ->set('dbConnection', 'sqlite')
            ->set('dbDatabase', $scratchDb);

        $component->call('next');

        $component->assertHasNoErrors();
        $this->assertSame(2, $component->get('step'));

        $envContents = file_get_contents($envPath);
        $this->assertStringContainsString('APP_NAME="Acme Platform"', $envContents); // quoted: contains a space
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $envContents);
        $this->assertStringContainsString("DB_DATABASE={$scratchDb}", $envContents);

        @unlink($scratchDb);
    }

    /**
     * Points the app's env file at a scratch file instead of the real
     * project .env, so tests that exercise the wizard's .env-writing logic
     * can never touch the real one. Returns the scratch file's path.
     */
    private function useScratchEnvFile(): string
    {
        $dir = sys_get_temp_dir();
        $filename = 'mlm_test_'.uniqid().'.env';
        $path = $dir.'/'.$filename;

        file_put_contents($path, "APP_NAME=Laravel\nAPP_KEY=base64:".base64_encode(random_bytes(32))."\n");

        $this->app->useEnvironmentPath($dir);
        $this->app->loadEnvironmentFrom($filename);

        return $path;
    }

    public function test_visiting_install_after_install_redirects_to_login(): void
    {
        SystemSetting::set('installed_at', now()->toDateTimeString());

        $this->get('/install')->assertRedirect(route('login'));
    }

    public function test_the_super_admin_created_by_the_wizard_can_earn_commissions(): void
    {
        // Sanity check that the tree root the wizard creates behaves like
        // any other root user for the commission engine.
        Volt::test('pages.install.wizard')
            ->set('companyName', 'Acme')
            ->set('supportEmail', 'support@acme.test')
            ->set('planType', 'unilevel')
            ->set('adminName', 'Jane Admin')
            ->set('adminEmail', 'jane@acme.test')
            ->set('adminPassword', 'password')
            ->set('adminPassword_confirmation', 'password')
            ->call('install');

        $admin = User::where('email', 'jane@acme.test')->first();

        $recruit = app(\App\Services\TreeService::class)->placeNewUser(User::factory()->make(), $admin, 'unilevel');

        $product = \App\Models\Product::factory()->create(['price' => 100, 'commission_value' => 100]);
        \App\Models\Order::create([
            'user_id' => $recruit->id,
            'product_id' => $product->id,
            'order_number' => 'ORD-'.uniqid(),
            'amount' => 100,
            'commission_value' => 100,
            'status' => \App\Models\Order::STATUS_COMPLETED,
            'order_date' => now(),
            'payment_status' => 'paid',
        ]);

        $this->assertSame(10.00, (float) Commission::where('user_id', $admin->id)->value('amount'));
    }
}
