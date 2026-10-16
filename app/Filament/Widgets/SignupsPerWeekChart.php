<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * New accounts per week (weeks start on Monday, UTC) over the last 12 weeks.
 */
class SignupsPerWeekChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Signups per week (12 weeks)';

    protected function getData(): array
    {
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks(11);

        $perDay = User::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $weeks = collect(range(0, 11))->map(fn (int $i) => $start->addWeeks($i));

        $totals = $weeks->map(fn (CarbonImmutable $week) => $perDay
            ->filter(fn ($total, string $day) => CarbonImmutable::parse($day)->startOfWeek()->equalTo($week))
            ->sum());

        return [
            'datasets' => [
                ['label' => 'Signups', 'data' => $totals->map(fn ($total) => (int) $total)->all()],
            ],
            'labels' => $weeks->map(fn (CarbonImmutable $week) => $week->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
