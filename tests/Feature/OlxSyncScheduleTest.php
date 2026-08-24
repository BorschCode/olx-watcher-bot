<?php

use App\Console\Commands\SyncOlxListings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('olx:sync is scheduled every minute without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'olx:sync'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

test('olx:sync skips when last run was less than an hour ago', function () {
    Cache::forever(SyncOlxListings::LAST_RUN_CACHE_KEY, now()->subMinutes(10)->timestamp);

    $this->artisan('olx:sync')
        ->expectsOutput('Skipped: last olx:sync run was less than 1 hour ago.')
        ->assertSuccessful();
});

test('olx:sync skips when the random next run slot has not arrived', function () {
    Cache::forever(SyncOlxListings::LAST_RUN_CACHE_KEY, now()->subHours(2)->timestamp);
    Cache::forever(SyncOlxListings::NEXT_RUN_CACHE_KEY, now()->addMinutes(20)->timestamp);

    $this->artisan('olx:sync')
        ->expectsOutput('Skipped: waiting for the next random olx:sync slot.')
        ->assertSuccessful();
});

test('olx:sync runs when last run was more than an hour ago', function () {
    Cache::forever(SyncOlxListings::LAST_RUN_CACHE_KEY, now()->subHours(2)->timestamp);
    Cache::forever(SyncOlxListings::NEXT_RUN_CACHE_KEY, now()->subMinute()->timestamp);

    $this->artisan('olx:sync')
        ->expectsOutput('No watchers configured.')
        ->assertSuccessful();
});

test('olx:sync stores last run and a next slot at least one hour ahead', function () {
    $this->artisan('olx:sync')
        ->expectsOutput('No watchers configured.')
        ->assertSuccessful();

    $lastRunAt = Cache::get(SyncOlxListings::LAST_RUN_CACHE_KEY);
    $nextRunAt = Cache::get(SyncOlxListings::NEXT_RUN_CACHE_KEY);

    expect($lastRunAt)->toBeInt()
        ->and($lastRunAt)->toBeGreaterThan(now()->subMinute()->timestamp)
        ->and($nextRunAt)->toBeInt()
        ->and($nextRunAt)->toBeGreaterThanOrEqual($lastRunAt + 3600)
        ->and($nextRunAt)->toBeLessThanOrEqual($lastRunAt + 3600 + (59 * 60));
});

test('olx:sync --watcher ignores the hourly throttle', function () {
    Cache::forever(SyncOlxListings::LAST_RUN_CACHE_KEY, now()->subMinutes(5)->timestamp);

    $this->artisan('olx:sync', ['--watcher' => 1])
        ->expectsOutput('No watchers configured.')
        ->assertSuccessful();

    expect(Cache::get(SyncOlxListings::LAST_RUN_CACHE_KEY))->toBe(now()->subMinutes(5)->timestamp);
});
