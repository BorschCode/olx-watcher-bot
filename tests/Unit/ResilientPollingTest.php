<?php

use App\Telegram\ResilientPolling;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    Polling::$FOREVER = true;
    ResilientPolling::$retryDelaySeconds = 5;
});

test('resilient polling continues after a telegram connection timeout', function () {
    Event::fake([MessageLogged::class]);
    Polling::$FOREVER = true;
    ResilientPolling::$retryDelaySeconds = 0;

    $attempts = 0;

    $bot = Mockery::mock(Nutgram::class);
    $bot->shouldReceive('getConfig')->andReturn(new Configuration);
    $bot->shouldReceive('getUpdates')->andReturnUsing(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new ConnectException(
                'cURL error 28: Operation timed out after 11002 milliseconds with 0 bytes received',
                new Request('POST', 'https://api.telegram.org/bot/getUpdates'),
            );
        }

        Polling::$FOREVER = false;

        return [];
    });

    (new ResilientPolling)->processUpdates($bot);

    expect($attempts)->toBe(2);

    Event::assertDispatched(
        MessageLogged::class,
        fn (MessageLogged $event): bool => $event->level === 'warning'
            && str_contains($event->message, 'Telegram polling')
            && str_contains((string) ($event->context['error'] ?? ''), 'timed out'),
    );
});
