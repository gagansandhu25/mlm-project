<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ModulesServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    ModulesServiceProvider::class,
    AdminPanelProvider::class,
    VoltServiceProvider::class,
];
