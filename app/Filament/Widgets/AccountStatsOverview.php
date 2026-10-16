<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use App\Models\Shot;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = User::count();
        $verified = User::whereNotNull('email_verified_at')->count();

        $white = Shot::live()->where('color', 'white')->count();
        $black = Shot::live()->where('color', 'black')->count();
        $shots = $white + $black;

        return [
            Stat::make('Users', number_format($total)),
            Stat::make('Verified users', number_format($verified))
                ->description($total > 0 ? round($verified / $total * 100).'% of users' : null),
            Stat::make('Active (7 days)', number_format($this->activeUsers(7))),
            Stat::make('Active (30 days)', number_format($this->activeUsers(30))),
            Stat::make('White : black', $shots > 0 ? round($white / $shots * 100).'% : '.round($black / $shots * 100).'%' : '—')
                ->description(number_format($shots).' shots on hearts now'),
        ];
    }

    /**
     * Users with any device seen (sign-in, refresh or sync) in the last N days.
     */
    private function activeUsers(int $days): int
    {
        return Device::where('last_seen_at', '>=', now()->subDays($days))->distinct()->count('user_id');
    }
}
