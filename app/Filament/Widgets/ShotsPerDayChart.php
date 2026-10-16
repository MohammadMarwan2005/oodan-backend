<?php

namespace App\Filament\Widgets;

use App\Models\Shot;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Shots recorded per day (by device time, UTC) over the last 30 days, including ones later
 * undone or deleted.
 */
class ShotsPerDayChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Shots per day (30 days)';

    protected function getData(): array
    {
        $start = CarbonImmutable::today()->subDays(29);

        $counts = Shot::query()
            ->where('shot_at', '>=', $start)
            ->selectRaw('DATE(shot_at) as day, color, COUNT(*) as total')
            ->groupBy('day', 'color')
            ->get()
            ->groupBy('color')
            ->map(fn ($rows) => $rows->pluck('total', 'day'));

        $days = collect(range(0, 29))->map(fn (int $i) => $start->addDays($i)->toDateString());

        return [
            'datasets' => [
                [
                    'label' => 'White',
                    'data' => $days->map(fn (string $day) => (int) ($counts['white'][$day] ?? 0))->all(),
                    'borderColor' => '#9ca3af',
                ],
                [
                    'label' => 'Black',
                    'data' => $days->map(fn (string $day) => (int) ($counts['black'][$day] ?? 0))->all(),
                    'borderColor' => '#111827',
                ],
            ],
            'labels' => $days->map(fn (string $day) => substr($day, 5))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
