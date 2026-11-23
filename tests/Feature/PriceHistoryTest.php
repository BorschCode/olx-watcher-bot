<?php

use App\Console\Commands\TrackOlxPrices;
use App\Models\PriceHistory;
use App\Models\SavedAd;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Http::fake(fn () => Http::response(['data' => []], 200));
});

test('price change creates one history row and updates saved ad price', function () {
    Http::fake(fn () => Http::response([
        'data' => [
            'params' => [
                ['key' => 'price', 'value' => ['converted_value' => 30000, 'value' => 30000]],
            ],
        ],
    ], 200));

    $savedAd = SavedAd::factory()->create([
        'olx_id' => 12345,
        'telegram_chat_id' => 'chat-1',
        'title' => 'Квартира в центрі',
        'price' => 10000,
        'track_price' => true,
    ]);

    $result = artisan('olx:track-prices')->assertSuccessful();

    expect(PriceHistory::count())->toBe(1)
        ->and(PriceHistory::first()->saved_ad_id)->toBe($savedAd->id)
        ->and(PriceHistory::first()->old_price)->toBe(10000)
        ->and(PriceHistory::first()->new_price)->toBe(30000)
        ->and(PriceHistory::first()->changed_at)->not->toBeNull()
        ->and($savedAd->refresh()->price)->toBe(30000);

    $result->expectsOutput('changed');
});

test('unchanged price creates no history row', function () {
    Http::fake(fn () => Http::response([
        'data' => [
            'params' => [
                ['key' => 'price', 'value' => ['converted_value' => 10000, 'value' => 10000]],
            ],
        ],
    ], 200));

    SavedAd::factory()->create([
        'olx_id' => 12345,
        'telegram_chat_id' => 'chat-1',
        'price' => 10000,
        'track_price' => true,
    ]);

    artisan('olx:track-prices')->assertSuccessful();

    expect(PriceHistory::count())->toBe(0);
});

test('fetch failure writes nothing', function () {
    Http::fake(fn () => Http::response([], 404));

    SavedAd::factory()->create([
        'olx_id' => 12345,
        'telegram_chat_id' => 'chat-1',
        'price' => 10000,
        'track_price' => true,
    ]);

    artisan('olx:track-prices')->assertSuccessful();

    expect(PriceHistory::count())->toBe(0);
});

test('untracked ads are skipped', function () {
    Http::fake(fn () => Http::response([
        'data' => [
            'params' => [
                ['key' => 'price', 'value' => ['converted_value' => 30000, 'value' => 30000]],
            ],
        ],
    ], 200));

    SavedAd::factory()->create([
        'olx_id' => 12345,
        'telegram_chat_id' => 'chat-1',
        'price' => 10000,
        'track_price' => false,
    ]);

    artisan('olx:track-prices')->assertSuccessful();

    expect(PriceHistory::count())->toBe(0);
});

test('dry-run does not persist changes', function () {
    Http::fake(fn () => Http::response([
        'data' => [
            'params' => [
                ['key' => 'price', 'value' => ['converted_value' => 30000, 'value' => 30000]],
            ],
        ],
    ], 200));

    $savedAd = SavedAd::factory()->create([
        'olx_id' => 12345,
        'telegram_chat_id' => 'chat-1',
        'price' => 10000,
        'track_price' => true,
    ]);

    artisan('olx:track-prices --dry-run')->assertSuccessful();

    expect(PriceHistory::count())->toBe(0)
        ->and($savedAd->refresh()->price)->toBe(10000);
});
