<?php

use App\Telegram\BotUsernameResolver;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('bot username resolver does not throw when telegram is unreachable', function () {
    config([
        'nutgram.token' => '123:abc',
        'nutgram.config.bot_name' => null,
    ]);
    Cache::flush();
    Event::fake([MessageLogged::class]);
    Http::fake([
        'https://api.telegram.org/*' => Http::failedConnection('Operation timed out'),
    ]);

    BotUsernameResolver::resolve(force: true);

    expect(config('nutgram.config.bot_name'))->toBeEmpty();

    Event::assertDispatched(
        MessageLogged::class,
        fn (MessageLogged $event): bool => $event->level === 'warning'
            && str_contains($event->message, 'Telegram bot username'),
    );
});
