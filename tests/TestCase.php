<?php

namespace Tests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every existing test assumes an already-configured app. Mark the
     * install wizard as already completed by default so the global
     * EnsureAppIsInstalled middleware doesn't redirect these requests;
     * InstallWizardTest explicitly clears this to exercise the
     * not-yet-installed behavior.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('system_settings')) {
            SystemSetting::set('installed_at', now()->toDateTimeString());
        }
    }
}
