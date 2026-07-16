<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesChart extends ChartWidget
{
    protected static ?string $heading = 'Sales Revenue (Last 30 Days)';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $days = collect(range(0, 29))->map(fn (int $i) => now()->subDays(29 - $i)->format('Y-m-d'));

        $totals = Order::where('status', Order::STATUS_COMPLETED)
            ->where('order_date', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(order_date) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $days->map(fn (string $day) => (float) ($totals[$day] ?? 0))->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
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
