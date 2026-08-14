<?php

use App\Support\OlxHttpClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('random fingerprint rotates user agent and includes client hints', function () {
    $client = new OlxHttpClient(maxRetries: 1);

    $first = $client->randomFingerprint('html');
    $second = $client->randomFingerprint('html');

    expect($first['id'])->not->toBeEmpty()
        ->and($first['headers']['User-Agent'])->not->toBeEmpty()
        ->and($first['headers']['Accept-Language'])->not->toBeEmpty()
        ->and($first['headers']['Referer'])->toBe('https://www.olx.ua/');

    $userAgents = [$first['headers']['User-Agent'], $second['headers']['User-Agent']];
    expect(count(array_unique($userAgents)))->toBeGreaterThanOrEqual(1);
});

test('build log context includes block diagnostics for failed responses', function () {
    Http::fake([
        'https://www.olx.ua/*' => Http::response(
            '<html>Attention Required! | Cloudflare</html>',
            403,
            ['cf-ray' => 'abc123', 'server' => 'cloudflare'],
        ),
    ]);

    $client = new OlxHttpClient(maxRetries: 1);
    $fingerprint = $client->randomFingerprint('html');
    $response = Http::get('https://www.olx.ua/test');

    $context = $client->buildLogContext($response, $fingerprint, 'https://www.olx.ua/test', 9, 1);

    expect($context['status'])->toBe(403)
        ->and($context['watcher'])->toBe(9)
        ->and($context['blocked_hint'])->toBe('cloudflare')
        ->and($context['response_headers']['cf-ray'])->toBe('abc123')
        ->and($context['response_body_preview'])->toContain('Cloudflare')
        ->and($context['fingerprint']['user_agent'])->toBe($fingerprint['headers']['User-Agent']);
});

test('retries 403 with a new fingerprint before failing', function () {
    Http::fake([
        'https://www.olx.ua/blocked' => Http::sequence()
            ->push('blocked', 403)
            ->push('<html>ok</html>', 200),
    ]);

    $client = new OlxHttpClient(maxRetries: 2);
    $response = $client->get('https://www.olx.ua/blocked', watcherId: 8);

    expect($response->successful())->toBeTrue();

    Http::assertSentCount(2);
});

test('applies configured proxy to outgoing requests', function () {
    config([
        'services.olx.proxy' => 'http://proxy.test:8080',
        'services.olx.proxy_verify' => false,
    ]);

    $client = new OlxHttpClient;
    $fingerprint = $client->randomFingerprint('html');
    $pending = $client->client($fingerprint['headers']);

    $reflection = new ReflectionClass($pending);
    $property = $reflection->getProperty('options');
    $property->setAccessible(true);
    $options = $property->getValue($pending);

    expect($options['proxy'])->toBe('http://proxy.test:8080')
        ->and($options['verify'])->toBeFalse();
});
