<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-10 px-4 sm:px-0">
        <div class="max-w-7xl mx-auto space-y-6">
            <x-card padding="p-4 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </x-card>

            <x-card padding="p-4 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </x-card>

            <x-card padding="p-4 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
