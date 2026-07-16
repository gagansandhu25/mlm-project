<?php

use App\Models\Rank;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TreeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $referral_code = '';

    public function mount(): void
    {
        $this->referral_code = (string) request()->query('ref', '');
    }

    /**
     * Handle an incoming registration request. Every member must be
     * sponsored by an existing referral code, which also determines
     * where they're placed in the genealogy tree.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'referral_code' => ['required', 'string', 'exists:users,referral_code'],
        ]);

        $sponsor = User::where('referral_code', $validated['referral_code'])->first();

        $user = DB::transaction(function () use ($validated, $sponsor) {
            $newUser = User::make([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_USER,
                'status' => User::STATUS_ACTIVE,
                'referral_code' => $this->generateReferralCode(),
                'join_date' => now(),
                'rank_id' => Rank::where('is_active', true)->orderBy('level')->value('id'),
            ]);

            return app(TreeService::class)->placeNewUser(
                $newUser,
                $sponsor,
                SystemSetting::get('active_plan_type', 'unilevel'),
            );
        });

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
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
    <form wire:submit="register">
        <!-- Referral Code -->
        <div>
            <x-input-label for="referral_code" :value="__('Referral / Sponsor Code')" />
            <x-text-input wire:model="referral_code" id="referral_code" class="block mt-1 w-full" type="text" name="referral_code" required autocomplete="off" />
            <x-input-error :messages="$errors->get('referral_code')" class="mt-2" />
        </div>

        <!-- Name -->
        <div class="mt-4">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</div>
