<?php

namespace App\Support;

use App\Filament\Resources\Watchers\WatcherResource;

class AppUrl
{
    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function adminWatchers(): string
    {
        return WatcherResource::getUrl('index');
    }
}
