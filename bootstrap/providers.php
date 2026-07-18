<?php

use App\Providers\AppServiceProvider;
use App\Providers\CommissionServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\PlacementServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    CommissionServiceProvider::class,
    PlacementServiceProvider::class,
    AdminPanelProvider::class,
    VoltServiceProvider::class,
];
