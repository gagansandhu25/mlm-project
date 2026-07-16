<?php

use App\Models\CommissionConfiguration;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RankSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.install')] class extends Component
{
    public int $step = 1;

    public bool $installing = false;

    // Step 1: application & database
    public string $appName = '';

    public string $dbConnection = 'mysql';

    public string $dbHost = '127.0.0.1';

    public string $dbPort = '3306';

    public string $dbDatabase = '';

    public string $dbUsername = '';

    public string $dbPassword = '';

    // Step 3: company details
    public string $companyName = '';

    public string $supportEmail = '';

    // Step 4: compensation plan
    public string $planType = 'unilevel';

    /** @var array<int, numeric-string|float|int> */
    public array $levelPercentages = [10, 5, 3, 2, 2, 1, 1, 1, 1, 1];

    public int $matrixWidth = 3;

    public float $binaryPairPercentage = 10;

    // Step 5: admin account
    public string $adminName = '';

    public string $adminEmail = '';

    public string $adminPassword = '';

    public string $adminPassword_confirmation = '';

    public function mount(): void
    {
        $this->appName = config('app.name', 'Laravel');
        $this->dbDatabase = database_path('database.sqlite');
    }

    public function updatedDbConnection(string $value): void
    {
        $this->dbPort = match ($value) {
            'pgsql' => '5432',
            default => '3306',
        };
    }

    public function appKeyIsSet(): bool
    {
        return filled(config('app.key'));
    }

    public function storageIsWritable(): bool
    {
        return is_writable(storage_path());
    }

    public function envIsWritable(): bool
    {
        $path = app()->environmentFilePath();

        return file_exists($path) ? is_writable($path) : is_writable(dirname($path));
    }

    public function databaseIsReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function next(): void
    {
        $rules = $this->rulesForStep($this->step);

        if ($rules !== []) {
            $this->validate($rules);
        }

        if ($this->step === 1) {
            if (! $this->testDatabaseConnection()) {
                $this->addError('dbDatabase', "Couldn't connect to the database with these details. Double-check them and try again.");

                return;
            }

            $this->applyEnvironmentConfiguration();

            if ($this->companyName === '') {
                $this->companyName = $this->appName;
            }
        }

        $this->step++;
    }

    public function back(): void
    {
        $this->step--;
    }

    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => array_merge(
                [
                    'appName' => ['required', 'string', 'max:255'],
                    'dbConnection' => ['required', 'in:mysql,pgsql,sqlite'],
                ],
                $this->dbConnection === 'sqlite'
                    ? ['dbDatabase' => ['required', 'string']]
                    : [
                        'dbHost' => ['required', 'string', 'max:255'],
                        'dbPort' => ['required', 'numeric'],
                        'dbDatabase' => ['required', 'string', 'max:255'],
                        'dbUsername' => ['required', 'string', 'max:255'],
                        'dbPassword' => ['nullable', 'string'],
                    ],
            ),
            3 => [
                'companyName' => ['required', 'string', 'max:255'],
                'supportEmail' => ['required', 'string', 'email', 'max:255'],
            ],
            4 => array_merge(
                ['planType' => ['required', 'in:unilevel,binary,matrix']],
                match ($this->planType) {
                    'binary' => ['binaryPairPercentage' => ['required', 'numeric', 'min:0', 'max:100']],
                    'matrix' => [
                        'matrixWidth' => ['required', 'integer', 'min:2', 'max:10'],
                        'levelPercentages.*' => ['required', 'numeric', 'min:0', 'max:100'],
                    ],
                    default => ['levelPercentages.*' => ['required', 'numeric', 'min:0', 'max:100']],
                },
            ),
            5 => [
                // No `unique:users,email` here: the `users` table doesn't
                // exist until migrations run, which is deferred to the
                // final install() step. Checked manually there instead,
                // after migrating, right before creating the account.
                'adminName' => ['required', 'string', 'max:255'],
                'adminEmail' => ['required', 'string', 'email', 'max:255'],
                'adminPassword' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ],
            default => [],
        };
    }

    /** Tests the submitted DB credentials directly via PDO — deliberately
     *  not touching Laravel's DB/Schema facades, so there's nothing to
     *  unwind if it fails. */
    private function testDatabaseConnection(): bool
    {
        try {
            $dsn = match ($this->dbConnection) {
                'sqlite' => "sqlite:{$this->dbDatabase}",
                'pgsql' => "pgsql:host={$this->dbHost};port={$this->dbPort};dbname={$this->dbDatabase}",
                default => "mysql:host={$this->dbHost};port={$this->dbPort};dbname={$this->dbDatabase}",
            };

            new \PDO(
                $dsn,
                $this->dbConnection === 'sqlite' ? null : $this->dbUsername,
                $this->dbConnection === 'sqlite' ? null : $this->dbPassword,
                [\PDO::ATTR_TIMEOUT => 5],
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Persists the validated app name + DB credentials to .env and
     *  generates APP_KEY if it's still blank — the moment a genuinely
     *  fresh clone becomes fixable through the browser alone. */
    private function applyEnvironmentConfiguration(): void
    {
        $values = [
            'APP_NAME' => $this->appName,
            'DB_CONNECTION' => $this->dbConnection,
            'DB_DATABASE' => $this->dbDatabase,
        ];

        if ($this->dbConnection !== 'sqlite') {
            $values['DB_HOST'] = $this->dbHost;
            $values['DB_PORT'] = $this->dbPort;
            $values['DB_USERNAME'] = $this->dbUsername;
            $values['DB_PASSWORD'] = $this->dbPassword;
        }

        $this->writeEnv($values);

        if (! $this->appKeyIsSet()) {
            Artisan::call('key:generate', ['--force' => true]);
        }
    }

    /** @param array<string, string> $values */
    private function writeEnv(array $values): void
    {
        $path = app()->environmentFilePath();
        $content = file_exists($path) ? file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->escapeEnvValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            $content = preg_match($pattern, $content)
                ? preg_replace($pattern, $line, $content)
                : rtrim($content, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $content);
    }

    private function escapeEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"]/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }

    public function install(): void
    {
        if ($this->installing) {
            return;
        }

        $this->validate($this->rulesForStep(5));
        $this->installing = true;

        // Deliberately outside the transaction below: migrations manage
        // their own internal transactions, and this is also what creates
        // the `sessions`/`cache` tables AppServiceProvider is checking for.
        Artisan::call('migrate', ['--force' => true]);

        // Only checkable now that `users` exists post-migration. On a
        // genuinely fresh install this table is empty and can never
        // collide; this only matters on a retry after a prior partial
        // install attempt left a row behind.
        if (User::where('email', $this->adminEmail)->exists()) {
            $this->addError('adminEmail', 'That email is already registered.');
            $this->step = 5;
            $this->installing = false;

            return;
        }

        DB::transaction(function (): User {
            (new RankSeeder)->run();

            SystemSetting::set('company_name', $this->companyName, 'general', 'string');
            SystemSetting::set('support_email', $this->supportEmail, 'general', 'string');
            SystemSetting::set('active_plan_type', $this->planType, 'commission', 'string');

            if ($this->planType === 'matrix') {
                SystemSetting::set('matrix_width', (string) $this->matrixWidth, 'commission', 'integer');
            }

            if ($this->planType === 'binary') {
                SystemSetting::set('binary_pair_percentage', (string) $this->binaryPairPercentage, 'commission', 'integer');
            }

            if (in_array($this->planType, ['unilevel', 'matrix'], true)) {
                foreach ($this->levelPercentages as $index => $percentage) {
                    CommissionConfiguration::query()->updateOrCreate(
                        ['plan_type' => $this->planType, 'level' => $index + 1],
                        ['percentage' => $percentage, 'cap' => 5000, 'is_active' => true, 'settings' => ['cap_period' => 'monthly']],
                    );
                }
            } else {
                CommissionConfiguration::query()->updateOrCreate(
                    ['plan_type' => 'binary', 'level' => 1],
                    ['percentage' => $this->binaryPairPercentage, 'cap' => 5000, 'is_active' => true, 'settings' => ['cap_period' => 'monthly']],
                );
            }

            $admin = User::create([
                'name' => $this->adminName,
                'email' => $this->adminEmail,
                'password' => Hash::make($this->adminPassword),
                'role' => User::ROLE_SUPER_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'referral_code' => $this->generateReferralCode(),
                'join_date' => now(),
                'depth' => 0,
            ]);
            // email_verified_at isn't mass-assignable (not in User::$fillable);
            // direct property assignment bypasses that guard, same as `path` below.
            $admin->email_verified_at = now();
            $admin->path = (string) $admin->id;
            $admin->save();

            SystemSetting::set('installed_at', now()->toDateTimeString(), 'general', 'datetime');

            return $admin;
        });

        // Not logging the admin in here on purpose: this request's session
        // started under the `file` driver (no `sessions` table existed yet
        // when it began), but the migration we just ran means the *next*
        // request will correctly use `database` sessions from the start —
        // a session ID minted under `file` has no matching row there, so
        // an Auth::login() here wouldn't survive the redirect. The admin
        // just logs in fresh with the credentials they set two steps ago.
        $this->redirect('/admin/login', navigate: false);
    }

    private function generateReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}; ?>

<div>
    <h1 class="text-lg font-semibold text-gray-900">Set up your platform</h1>
    <p class="text-sm text-gray-500 mt-1">Step {{ $step }} of 6</p>

    {{-- Step 1: Application & Database --}}
    @if ($step === 1)
        <div class="mt-6 space-y-4">
            <p class="text-sm text-gray-600">Tell us where your database lives &mdash; we'll create the tables and an admin account for you.</p>

            @unless ($this->envIsWritable())
                <p class="text-sm text-red-600">Warning: <code>.env</code> isn't writable by the web server. These settings won't be able to be saved until that's fixed.</p>
            @endunless

            <div>
                <x-input-label for="appName" value="Application name" />
                <x-text-input wire:model="appName" id="appName" class="block mt-1 w-full" type="text" required autofocus />
                <x-input-error :messages="$errors->get('appName')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="dbConnection" value="Database type" />
                <select wire:model.live="dbConnection" id="dbConnection" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="mysql">MySQL</option>
                    <option value="pgsql">PostgreSQL</option>
                    <option value="sqlite">SQLite</option>
                </select>
            </div>

            @if ($dbConnection === 'sqlite')
                <div>
                    <x-input-label for="dbDatabase" value="Database file path" />
                    <x-text-input wire:model="dbDatabase" id="dbDatabase" class="block mt-1 w-full" type="text" required />
                    <x-input-error :messages="$errors->get('dbDatabase')" class="mt-2" />
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <x-input-label for="dbHost" value="Host" />
                        <x-text-input wire:model="dbHost" id="dbHost" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('dbHost')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="dbPort" value="Port" />
                        <x-text-input wire:model="dbPort" id="dbPort" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('dbPort')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="dbDatabase" value="Database name" />
                    <x-text-input wire:model="dbDatabase" id="dbDatabase" class="block mt-1 w-full" type="text" required />
                    <x-input-error :messages="$errors->get('dbDatabase')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="dbUsername" value="Username" />
                        <x-text-input wire:model="dbUsername" id="dbUsername" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('dbUsername')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="dbPassword" value="Password" />
                        <x-text-input wire:model="dbPassword" id="dbPassword" class="block mt-1 w-full" type="password" />
                        <x-input-error :messages="$errors->get('dbPassword')" class="mt-2" />
                    </div>
                </div>
            @endif
        </div>

        <div class="flex justify-end mt-6">
            <x-primary-button wire:click="next">Continue</x-primary-button>
        </div>
    @endif

    {{-- Step 2: Welcome / requirements --}}
    @if ($step === 2)
        <div class="mt-6 space-y-3">
            <p class="text-sm text-gray-600">Your database connection is working. Here's a final check before we continue.</p>

            <div class="flex items-center gap-2 text-sm">
                @if ($this->appKeyIsSet())
                    <span class="text-green-600">&#10003;</span> <span>Application key is set</span>
                @else
                    <span class="text-red-600">&#10007;</span> <span>Application key is missing</span>
                @endif
            </div>

            <div class="flex items-center gap-2 text-sm">
                @if ($this->storageIsWritable())
                    <span class="text-green-600">&#10003;</span> <span>Storage directory is writable</span>
                @else
                    <span class="text-red-600">&#10007;</span> <span>Storage directory is not writable</span>
                @endif
            </div>

            <div class="flex items-center gap-2 text-sm">
                @if ($this->databaseIsReachable())
                    <span class="text-green-600">&#10003;</span> <span>Database connection is working</span>
                @else
                    <span class="text-red-600">&#10007;</span> <span>Can't connect to the database</span>
                @endif
            </div>

            <p class="text-sm text-gray-500">Tables and seed data will be created automatically when you finish this wizard.</p>
        </div>

        <div class="flex justify-between mt-6">
            <x-secondary-button wire:click="back">Back</x-secondary-button>
            <x-primary-button wire:click="next">Continue</x-primary-button>
        </div>
    @endif

    {{-- Step 3: Company details --}}
    @if ($step === 3)
        <div class="mt-6 space-y-4">
            <div>
                <x-input-label for="companyName" value="Company name" />
                <x-text-input wire:model="companyName" id="companyName" class="block mt-1 w-full" type="text" required autofocus />
                <x-input-error :messages="$errors->get('companyName')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="supportEmail" value="Support email" />
                <x-text-input wire:model="supportEmail" id="supportEmail" class="block mt-1 w-full" type="email" required />
                <x-input-error :messages="$errors->get('supportEmail')" class="mt-2" />
            </div>
        </div>

        <div class="flex justify-between mt-6">
            <x-secondary-button wire:click="back">Back</x-secondary-button>
            <x-primary-button wire:click="next">Continue</x-primary-button>
        </div>
    @endif

    {{-- Step 4: Compensation plan --}}
    @if ($step === 4)
        <div class="mt-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="border rounded-lg p-3 cursor-pointer {{ $planType === 'unilevel' ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-300' }}">
                    <input type="radio" wire:model.live="planType" value="unilevel" class="sr-only">
                    <span class="block font-medium text-sm">Unilevel</span>
                    <span class="block text-xs text-gray-500 mt-1">Unlimited width, paid by depth per level.</span>
                </label>
                <label class="border rounded-lg p-3 cursor-pointer {{ $planType === 'binary' ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-300' }}">
                    <input type="radio" wire:model.live="planType" value="binary" class="sr-only">
                    <span class="block font-medium text-sm">Binary</span>
                    <span class="block text-xs text-gray-500 mt-1">Two legs, paid on matched left/right volume.</span>
                </label>
                <label class="border rounded-lg p-3 cursor-pointer {{ $planType === 'matrix' ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-300' }}">
                    <input type="radio" wire:model.live="planType" value="matrix" class="sr-only">
                    <span class="block font-medium text-sm">Matrix</span>
                    <span class="block text-xs text-gray-500 mt-1">Fixed-width tree, paid by depth per level.</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('planType')" class="mt-2" />

            @if ($planType === 'matrix')
                <div>
                    <x-input-label for="matrixWidth" value="Matrix width (children per member)" />
                    <x-text-input wire:model="matrixWidth" id="matrixWidth" class="block mt-1 w-full sm:w-32" type="number" min="2" max="10" />
                    <x-input-error :messages="$errors->get('matrixWidth')" class="mt-2" />
                </div>
            @endif

            @if (in_array($planType, ['unilevel', 'matrix'], true))
                <div>
                    <x-input-label value="Level payout percentages" />
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-1">
                        @foreach ($levelPercentages as $index => $percentage)
                            <div>
                                <label class="text-xs text-gray-500">Level {{ $index + 1 }}</label>
                                <x-text-input wire:model="levelPercentages.{{ $index }}" class="block mt-1 w-full" type="number" step="0.01" min="0" max="100" />
                            </div>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('levelPercentages.*')" class="mt-2" />
                </div>
            @else
                <div>
                    <x-input-label for="binaryPairPercentage" value="Pair percentage (% of matched volume)" />
                    <x-text-input wire:model="binaryPairPercentage" id="binaryPairPercentage" class="block mt-1 w-full sm:w-32" type="number" step="0.01" min="0" max="100" />
                    <x-input-error :messages="$errors->get('binaryPairPercentage')" class="mt-2" />
                </div>
            @endif
        </div>

        <div class="flex justify-between mt-6">
            <x-secondary-button wire:click="back">Back</x-secondary-button>
            <x-primary-button wire:click="next">Continue</x-primary-button>
        </div>
    @endif

    {{-- Step 5: Admin account --}}
    @if ($step === 5)
        <div class="mt-6 space-y-4">
            <div>
                <x-input-label for="adminName" value="Your name" />
                <x-text-input wire:model="adminName" id="adminName" class="block mt-1 w-full" type="text" required autofocus />
                <x-input-error :messages="$errors->get('adminName')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="adminEmail" value="Email" />
                <x-text-input wire:model="adminEmail" id="adminEmail" class="block mt-1 w-full" type="email" required />
                <x-input-error :messages="$errors->get('adminEmail')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="adminPassword" value="Password" />
                <x-text-input wire:model="adminPassword" id="adminPassword" class="block mt-1 w-full" type="password" required />
                <x-input-error :messages="$errors->get('adminPassword')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="adminPassword_confirmation" value="Confirm password" />
                <x-text-input wire:model="adminPassword_confirmation" id="adminPassword_confirmation" class="block mt-1 w-full" type="password" required />
            </div>
        </div>

        <div class="flex justify-between mt-6">
            <x-secondary-button wire:click="back">Back</x-secondary-button>
            <x-primary-button wire:click="next">Continue</x-primary-button>
        </div>
    @endif

    {{-- Step 6: Review & install --}}
    @if ($step === 6)
        <div class="mt-6 space-y-4 text-sm">
            <div>
                <h2 class="font-medium text-gray-900">Company</h2>
                <p class="text-gray-600">{{ $companyName }} &middot; {{ $supportEmail }}</p>
            </div>

            <div>
                <h2 class="font-medium text-gray-900">Compensation plan</h2>
                <p class="text-gray-600">
                    {{ ucfirst($planType) }}
                    @if ($planType === 'binary')
                        &mdash; {{ $binaryPairPercentage }}% pair bonus
                    @else
                        &mdash; {{ implode('%, ', $levelPercentages) }}% across {{ count($levelPercentages) }} levels
                        @if ($planType === 'matrix')
                            , width {{ $matrixWidth }}
                        @endif
                    @endif
                </p>
            </div>

            <div>
                <h2 class="font-medium text-gray-900">Administrator</h2>
                <p class="text-gray-600">{{ $adminName }} &middot; {{ $adminEmail }}</p>
            </div>
        </div>

        <div class="flex justify-between mt-6">
            <x-secondary-button wire:click="back" wire:loading.attr="disabled" wire:target="install">Back</x-secondary-button>
            <x-primary-button wire:click="install" wire:loading.attr="disabled" wire:target="install">
                <span wire:loading.remove wire:target="install">Install</span>
                <span wire:loading wire:target="install">Installing&hellip;</span>
            </x-primary-button>
        </div>
    @endif
</div>
