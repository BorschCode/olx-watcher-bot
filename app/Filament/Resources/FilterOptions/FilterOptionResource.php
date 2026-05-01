<?php

namespace App\Filament\Resources\FilterOptions;

use App\Filament\Resources\FilterOptions\Pages\ManageFilterOptions;
use App\Models\FilterOption;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FilterOptionResource extends Resource
{
    protected static ?string $model = FilterOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Filter Options';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('group')
                    ->options(self::groupOptions())
                    ->required()
                    ->native(false),

                TextInput::make('label')
                    ->required()
                    ->maxLength(255),

                TextInput::make('key')
                    ->label('OLX API Key')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('search[filter_enum_condition][]'),

                TextInput::make('value')
                    ->label('OLX API Value')
                    ->maxLength(255)
                    ->placeholder('new'),

                Toggle::make('has_range')
                    ->label('Has from/to range')
                    ->helperText('Enable for options like price that need a from/to value pair.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group')
                    ->badge()
                    ->sortable(),

                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('key')
                    ->label('API Key')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (FilterOption $record): string => $record->key),

                TextColumn::make('value')
                    ->label('API Value')
                    ->placeholder('—'),

                IconColumn::make('has_range')
                    ->label('Range')
                    ->boolean(),

                TextColumn::make('watchers_count')
                    ->counts('watchers')
                    ->label('Watchers')
                    ->sortable(),
            ])
            ->defaultSort('group')
            ->filters([
                SelectFilter::make('group')
                    ->options(self::groupOptions()),
            ])
            ->recordActions([
                EditAction::make(),
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
            'index' => ManageFilterOptions::route('/'),
        ];
    }

    /** @return array<string, string> */
    public static function groupOptions(): array
    {
        return [
            'condition' => 'Стан',
            'price' => 'Ціна',
            'currency' => 'Валюта',
            'location' => 'Локація',
            'other' => 'Інше',
        ];
    }
}
