<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OlxCacheOverview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class SystemTools extends Page
{
    protected string $view = 'filament.pages.system-tools';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Система';

    protected static ?string $title = 'Системні інструменти';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('flushOlxCache')
                ->label('Очистити кеш OLX')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Очистити кеш OLX?')
                ->modalDescription('Всі кешовані записи оголошень будуть видалені. Кнопка "Зберегти" в старих повідомленнях Telegram все одно спрацює — дані будуть завантажені напряму з OLX.')
                ->modalSubmitActionLabel('Очистити')
                ->action(function (): void {
                    $deleted = DB::table('cache')
                        ->where('key', 'like', '%olx_offer_%')
                        ->delete();

                    Notification::make()
                        ->title("Кеш очищено: {$deleted} записів видалено")
                        ->success()
                        ->send();
                }),
        ];
    }

    /** @return array<class-string> */
    public function getWidgets(): array
    {
        return [
            OlxCacheOverview::class,
        ];
    }
}
