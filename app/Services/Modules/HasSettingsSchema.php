<?php

namespace App\Services\Modules;

/**
 * Shared by PlanModule and IncomeModule: lets a module contribute its own
 * settings fields to the admin Settings page without that page needing to
 * know the module exists.
 */
interface HasSettingsSchema
{
    /**
     * Filament form components for this module's own settings. Return an
     * empty array if the module needs no extra settings, or manages its
     * config on a dedicatedSettingsPage() instead.
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    public function settingsSchema(): array;

    /** @return array<string, mixed> initial values for settingsSchema()'s fields */
    public function settingsData(): array;

    /** @param array<string, mixed> $state */
    public function saveSettings(array $state): void;

    /**
     * FQCN of a bespoke Filament Page this module manages its own config
     * on instead of (or in addition to) settingsSchema() — e.g. Package
     * Tier's reorderable tier repeater doesn't fit a few inline fields.
     * Null if the module has no such page.
     */
    public function dedicatedSettingsPage(): ?string;
}
