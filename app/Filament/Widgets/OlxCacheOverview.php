<?php

namespace App\Filament\Widgets;

use App\Models\SavedAd;
use App\Models\Watcher;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OlxCacheOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $cachedCount = DB::table('cache')
            ->where('key', 'like', '%olx_offer_%')
            ->where('expiration', '>', now()->timestamp)
            ->count();

        $totalSaved = SavedAd::count();
        $activeSaved = SavedAd::where('valid_until', '>=', now())->count();

        $totalWatchers = Watcher::count();
        $activeWatchers = Watcher::active()->count();

        return [
            Stat::make('Збережені оголошення', $totalSaved)
                ->description("{$activeSaved} активних")
                ->descriptionIcon('heroicon-m-bookmark')
                ->color('success'),

            Stat::make('Кеш оголошень', $cachedCount)
                ->description('Активних записів в кеші')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color($cachedCount > 0 ? 'info' : 'gray'),

            Stat::make('Вотчери', $totalWatchers)
                ->description("{$activeWatchers} активних")
                ->descriptionIcon('heroicon-m-bell')
                ->color('warning'),
        ];
    }
}
