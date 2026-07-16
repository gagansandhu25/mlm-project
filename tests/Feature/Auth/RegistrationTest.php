<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register_with_a_valid_sponsor_code(): void
    {
        $sponsor = User::factory()->create(['depth' => 0]);
        $sponsor->path = (string) $sponsor->id;
        $sponsor->save();

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('referral_code', $sponsor->referral_code);

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $newUser = User::where('email', 'test@example.com')->first();
        $this->assertSame($sponsor->id, $newUser->sponsor_id);
        $this->assertSame($sponsor->id, $newUser->parent_id);
        $this->assertNotNull($newUser->referral_code);
    }

    public function test_registration_requires_a_valid_referral_code(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('referral_code', 'DOES-NOT-EXIST');

        $component->call('register');

        $component->assertHasErrors(['referral_code']);
        $this->assertGuest();
    }
}
