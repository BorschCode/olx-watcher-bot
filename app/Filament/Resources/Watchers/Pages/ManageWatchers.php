<?php

namespace App\Filament\Resources\Watchers\Pages;

use App\Filament\Resources\Watchers\WatcherResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWatchers extends ManageRecords
{
    protected static string $resource = WatcherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(fn (array $data): array => WatcherResource::normalizeFormData($data)),
        ];
    }
}
