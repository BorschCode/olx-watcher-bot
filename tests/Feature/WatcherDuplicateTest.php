<?php

use App\Enums\HttpMethod;
use App\Enums\WatcherSource;
use App\Models\Category;
use App\Models\City;
use App\Models\FilterOption;
use App\Models\Watcher;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Тестова категорія',
        'slug' => 'testova-kategoriya',
        'olx_id' => 1,
    ]);

    $this->city = City::create([
        'name' => 'Київ',
        'slug' => 'kyiv',
        'olx_id' => 268,
        'region_id' => 25,
    ]);

    $this->watcher = Watcher::create([
        'telegram_chat_id' => 'chat-1',
        'source' => WatcherSource::Olx->value,
        'method' => HttpMethod::GetHtml->value,
        'category_id' => $this->category->id,
        'city_id' => $this->city->id,
        'url' => 'https://www.olx.ua/uk/nedvizhimost/doma/prodazha-domov/kiev/?currency=USD',
        'last_seen_id' => 100,
        'is_active' => true,
    ]);
});

test('duplicate clones watcher fields and resets last_seen_id', function () {
    $clone = $this->watcher->duplicate('chat-2');

    expect($clone->id)->not->toBe($this->watcher->id)
        ->and($clone->telegram_chat_id)->toBe('chat-2')
        ->and($clone->source)->toBe($this->watcher->source)
        ->and($clone->method)->toBe($this->watcher->method)
        ->and($clone->category_id)->toBe($this->watcher->category_id)
        ->and($clone->city_id)->toBe($this->watcher->city_id)
        ->and($clone->url)->toBe($this->watcher->url)
        ->and($clone->last_seen_id)->toBeNull()
        ->and($clone->is_active)->toBeTrue();
});

test('duplicate keeps filter options pivot values', function () {
    $option = FilterOption::create([
        'group' => 'rooms',
        'label' => 'Кімнат: 2',
        'key' => 'filter_enum_number_of_rooms_string',
        'value' => 'dvuhkomnatnye',
        'has_range' => false,
    ]);

    $this->watcher->filterOptions()->attach($option->id, ['value_from' => '2', 'value_to' => '3']);

    $clone = $this->watcher->duplicate('chat-2');

    expect($clone->filterOptions)->toHaveCount(1)
        ->and($clone->filterOptions->first()->pivot->value_from)->toBe('2')
        ->and($clone->filterOptions->first()->pivot->value_to)->toBe('3');
});
