<?php

namespace App\Filament\Resources\FilterOptions\Pages;

use App\Filament\Resources\FilterOptions\FilterOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFilterOptions extends ManageRecords
{
    protected static string $resource = FilterOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
