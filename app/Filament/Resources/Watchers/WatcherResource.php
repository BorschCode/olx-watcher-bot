<?php

namespace App\Filament\Resources\Watchers;

use App\Enums\HttpMethod;
use App\Filament\Resources\Watchers\Pages\ManageWatchers;
use App\Models\Category;
use App\Models\City;
use App\Models\Watcher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WatcherResource extends Resource
{
    protected static ?string $model = Watcher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telegram_chat_id')
                    ->label('Telegram Chat ID')
                    ->required()
                    ->maxLength(255),

                Select::make('method')
                    ->options(collect(HttpMethod::cases())->mapWithKeys(
                        fn (HttpMethod $m) => [$m->value => $m->label()]
                    ))
                    ->required()
                    ->default(HttpMethod::Get->value)
                    ->native(false)
                    ->live(),

                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('city_id')
                    ->label('City')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                // ── GET (REST) ────────────────────────────────────────────
                Section::make('REST API (GET)')
                    ->schema([
                        TextInput::make('url')
                            ->label('Base URL')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->suffixAction(
                                Action::make('generateUrl')
                                    ->label('Generate')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->action(function (Get $get, Set $set): void {
                                        $categoryId = $get('category_id');
                                        $cityId = $get('city_id');

                                        if (! $categoryId) {
                                            return;
                                        }

                                        $category = Category::find($categoryId);
                                        $city = $cityId ? City::find($cityId) : null;

                                        $slug = ltrim($category->slug, '/');
                                        $base = 'https://www.olx.ua/'.$slug.'/';

                                        if ($city) {
                                            $base .= $city->slug.'/';
                                        }

                                        $set('url', $base);
                                    })
                            ),

                        CheckboxList::make('filterOptions')
                            ->label('Filter Options')
                            ->relationship('filterOptions', 'label')
                            ->columnSpanFull(),

                        Placeholder::make('final_url')
                            ->label('Final URL Preview')
                            ->content(fn (?Watcher $record): string => $record?->final_url ?? '—')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => $get('method') === HttpMethod::Get->value)
                    ->columnSpanFull(),

                // ── POST (GraphQL) ────────────────────────────────────────
                Section::make('GraphQL (POST)')
                    ->description('Search parameters are built automatically from category, city, and filters below.')
                    ->schema([
                        CheckboxList::make('filterOptions')
                            ->label('Filter Options')
                            ->relationship('filterOptions', 'label')
                            ->columnSpanFull(),

                        Placeholder::make('graphql_preview')
                            ->label('Search Parameters Preview')
                            ->content(function (?Watcher $record): string {
                                if (! $record) {
                                    return 'Save the watcher first to preview search parameters.';
                                }

                                $record->load('filterOptions', 'city');
                                $params = $record->buildSearchParameters();

                                return collect($params)
                                    ->map(fn (array $p) => "{$p['key']} = {$p['value']}")
                                    ->join("\n");
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => $get('method') === HttpMethod::Post->value)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('telegram_chat_id')
                    ->label('Chat ID')
                    ->searchable(),

                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (HttpMethod $state): string => $state->label())
                    ->color(fn (HttpMethod $state): string => match ($state) {
                        HttpMethod::Get => 'success',
                        HttpMethod::Post => 'warning',
                    }),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('city.name')
                    ->label('City')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(45)
                    ->copyable()
                    ->tooltip(fn (Watcher $record): ?string => $record->url),

                TextColumn::make('filterOptions.label')
                    ->label('Filters')
                    ->badge()
                    ->separator(','),

                TextColumn::make('last_seen_id')
                    ->label('Last Seen ID')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->options(collect(HttpMethod::cases())->mapWithKeys(
                        fn (HttpMethod $m) => [$m->value => $m->label()]
                    )),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => self::normalizeFormData($data)),
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
            'index' => ManageWatchers::route('/'),
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    public static function normalizeFormData(array $data): array
    {
        if (($data['method'] ?? null) === HttpMethod::Post->value) {
            $data['request_body'] = null;
            $data['url'] = 'https://www.olx.ua/apigateway/graphql';
        } else {
            $data['request_body'] = null;
        }

        return $data;
    }
}
