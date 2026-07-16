<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MemberGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'New Members (Last 30 Days)';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $days = collect(range(0, 29))->map(fn (int $i) => now()->subDays(29 - $i)->format('Y-m-d'));

        $counts = User::where('role', User::ROLE_USER)
            ->where('join_date', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(join_date) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'datasets' => [
                [
                    'label' => 'New members',
                    'data' => $days->map(fn (string $day) => (int) ($counts[$day] ?? 0))->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $days->map(fn (string $day) => Carbon::parse($day)->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
