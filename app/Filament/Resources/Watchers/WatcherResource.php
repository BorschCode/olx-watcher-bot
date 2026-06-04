<?php

namespace App\Filament\Resources\Watchers;

use App\Enums\HttpMethod;
use App\Enums\WatcherSource;
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
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

                // Source selects the system; method only applies to OLX
                Select::make('source')
                    ->label('Система')
                    ->options(collect(WatcherSource::cases())->mapWithKeys(
                        fn (WatcherSource $s) => [$s->value => $s->label()]
                    ))
                    ->required()
                    ->default(WatcherSource::Olx->value)
                    ->native(false)
                    ->live(),

                Select::make('method')
                    ->label('Метод (OLX)')
                    ->options(collect(HttpMethod::cases())->mapWithKeys(
                        fn (HttpMethod $m) => [$m->value => $m->label()]
                    ))
                    ->default(HttpMethod::Get->value)
                    ->native(false)
                    ->live()
                    ->visible(fn (Get $get): bool => $get('source') === WatcherSource::Olx->value),

                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(fn (Get $get): bool => $get('source') === WatcherSource::Olx->value),

                Select::make('city_id')
                    ->label('City')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(fn (Get $get): bool => $get('source') === WatcherSource::Olx->value),

                // ── OLX: GET (REST) ───────────────────────────────────────
                Section::make('OLX – REST API (GET)')
                    ->schema([
                        TextInput::make('url')
                            ->label('Base URL')
                            ->maxLength(2048)
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
                            ->relationship(
                                'filterOptions',
                                'label',
                                fn (Builder $query) => $query->forMethod(HttpMethod::Get->value),
                            )
                            ->columnSpanFull(),

                        Placeholder::make('final_url')
                            ->label('Final URL Preview')
                            ->content(fn (?Watcher $record): string => $record?->final_url ?? '—')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => $get('source') === WatcherSource::Olx->value
                        && $get('method') === HttpMethod::Get->value)
                    ->columnSpanFull(),

                // ── OLX: GET HTML ─────────────────────────────────────────
                Section::make('OLX – GET HTML')
                    ->description('Paste the full OLX search page URL. Filters and sorting must be included in the URL directly.')
                    ->schema([
                        TextInput::make('url')
                            ->label('URL пошукової сторінки')
                            ->maxLength(2048)
                            ->columnSpanFull()
                            ->placeholder('https://www.olx.ua/uk/nedvizhimost/...'),
                    ])
                    ->visible(fn (Get $get): bool => $get('source') === WatcherSource::Olx->value
                        && $get('method') === HttpMethod::GetHtml->value)
                    ->columnSpanFull(),

                // ── OLX: POST (GraphQL) ───────────────────────────────────
                Section::make('OLX – GraphQL (POST)')
                    ->description('Search parameters are built automatically from category, city, and filters below.')
                    ->schema([
                        CheckboxList::make('filterOptions')
                            ->label('Filter Options')
                            ->relationship(
                                'filterOptions',
                                'label',
                                fn (Builder $query) => $query->forMethod(HttpMethod::Post->value),
                            )
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
                    ->visible(fn (Get $get): bool => $get('source') === WatcherSource::Olx->value
                        && $get('method') === HttpMethod::Post->value)
                    ->columnSpanFull(),

                // ── Auto.ria ──────────────────────────────────────────────
                Section::make('Auto.ria – API Search URL')
                    ->description(fn (?Watcher $record): string => $record?->url
                        ? "Збережений URL: {$record->url}"
                        : 'Вставте URL пошуку з developers.ria.com з потрібними фільтрами. Не включайте api_key, page та countpage — вони додаються автоматично.')
                    ->schema([
                        TextInput::make('url')
                            ->label('API Search URL')
                            ->maxLength(2048)
                            ->columnSpanFull()
                            ->placeholder('https://developers.ria.com/auto/search?category_id=1&state[0]=10&...'),
                    ])
                    ->visible(fn (Get $get): bool => $get('source') === WatcherSource::AutoRia->value)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Активний'),

                TextColumn::make('telegram_chat_id')
                    ->label('Chat ID')
                    ->searchable(),

                TextColumn::make('source')
                    ->label('Система')
                    ->badge()
                    ->formatStateUsing(fn (WatcherSource $state): string => $state->label())
                    ->color(fn (WatcherSource $state): string => $state->color()),

                TextColumn::make('method')
                    ->label('Метод')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?HttpMethod $state): string => $state?->label() ?? '—')
                    ->placeholder('—'),

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
                SelectFilter::make('source')
                    ->label('Система')
                    ->options(collect(WatcherSource::cases())->mapWithKeys(
                        fn (WatcherSource $s) => [$s->value => $s->label()]
                    )),

                SelectFilter::make('method')
                    ->label('Метод')
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
        $data['request_body'] = null;

        if (($data['source'] ?? null) === WatcherSource::AutoRia->value) {
            $data['method'] = null;
        } elseif (($data['method'] ?? null) === HttpMethod::Post->value) {
            $data['url'] = 'https://www.olx.ua/apigateway/graphql';
        }

        return $data;
    }
}
