<?php

namespace App\Filament\Resources\SavedAds\Pages;

use App\Filament\Resources\SavedAds\SavedAdResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSavedAds extends ManageRecords
{
    protected static string $resource = SavedAdResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
