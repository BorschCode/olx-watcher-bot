<?php

namespace App\Filament\Resources\SavedAds;

use App\Filament\Resources\SavedAds\Pages\ManageSavedAds;
use App\Models\SavedAd;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SavedAdResource extends Resource
{
    protected static ?string $model = SavedAd::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static ?string $navigationLabel = 'Збережені оголошення';

    protected static ?string $modelLabel = 'Збережене оголошення';

    protected static ?string $pluralModelLabel = 'Збережені оголошення';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('title')
                    ->label('Назва')
                    ->columnSpanFull(),

                TextEntry::make('url')
                    ->label('URL')
                    ->url(fn (SavedAd $record): string => $record->url)
                    ->openUrlInNewTab()
                    ->columnSpanFull(),

                TextEntry::make('price')
                    ->label('Ціна')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state, 0, '.', ' ').' грн' : '—'),

                TextEntry::make('telegram_chat_id')
                    ->label('Telegram Chat ID'),

                TextEntry::make('published_at')
                    ->label('Опубліковано')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextEntry::make('refreshed_at')
                    ->label('Оновлено')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextEntry::make('valid_until')
                    ->label('Активно до')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextEntry::make('created_at')
                    ->label('Збережено')
                    ->dateTime('d.m.Y H:i'),
            ])->columns(2),

            Section::make('Фото')->schema([
                ImageEntry::make('images')
                    ->label('')
                    ->height(200)
                    ->columnSpanFull(),
            ])->visible(fn (SavedAd $record): bool => ! empty($record->images)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Назва')
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn (SavedAd $record): string => $record->title),

                TextColumn::make('telegram_chat_id')
                    ->label('Chat ID')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('price')
                    ->label('Ціна')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state, 0, '.', ' ').' грн' : '—')
                    ->sortable(),

                TextColumn::make('images')
                    ->label('Фото')
                    ->formatStateUsing(fn (?array $state): string => $state ? count($state).' шт.' : '—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(40)
                    ->url(fn (SavedAd $record): string => $record->url)
                    ->openUrlInNewTab()
                    ->tooltip(fn (SavedAd $record): string => $record->url),

                TextColumn::make('published_at')
                    ->label('Опубліковано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('valid_until')
                    ->label('Активно до')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Збережено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Активні')
                    ->query(fn (Builder $query) => $query->where('valid_until', '>=', now()))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSavedAds::route('/'),
        ];
    }
}
