<?php

use App\Support\OlxHttpClient;
use App\Support\OlxProxyPool;
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

test('OLX client rotates pool proxies between attempts', function () {
    $path = tempnam(sys_get_temp_dir(), 'olx-proxies-');
    file_put_contents($path, implode(PHP_EOL, [
        'socks5://149.62.186.244:1080',
        'http://5.45.126.128:8080',
    ]));

    try {
        $client = new OlxHttpClient(
            maxRetries: 1,
            proxyPool: new OlxProxyPool(path: $path),
        );
        $headers = $client->randomFingerprint('html')['headers'];

        $first = $client->client($headers);
        $second = $client->client($headers);

        $reflection = new ReflectionClass($first);
        $property = $reflection->getProperty('options');

        $firstOptions = $property->getValue($first);
        $secondOptions = $property->getValue($second);
        Http::fake([
            'https://www.olx.ua/blocked' => Http::response('blocked', 403),
        ]);
        $context = $client->buildLogContext(
            Http::get('https://www.olx.ua/blocked'),
            ['id' => 'test', 'browser' => 'Test', 'platform' => 'Test', 'headers' => $headers],
            'https://www.olx.ua/blocked',
            8,
            1,
        );

        expect($firstOptions['proxy'])->toBe('socks5://149.62.186.244:1080')
            ->and($secondOptions['proxy'])->toBe('http://5.45.126.128:8080')
            ->and($context['proxy'])->toBe('5.45.126.128:8080');
    } finally {
        unlink($path);
    }
});

test('proxy pool rotates valid mixed-protocol endpoints', function () {
    $path = tempnam(sys_get_temp_dir(), 'olx-proxies-');
    file_put_contents($path, implode(PHP_EOL, [
        '# refreshed proxy list',
        'socks5://149.62.186.244:1080',
        'not-a-proxy',
        'http://5.45.126.128:8080',
        'socks5://149.62.186.244:1080',
    ]));

    try {
        $pool = new OlxProxyPool(path: $path);

        expect($pool->next())->toBe('socks5://149.62.186.244:1080')
            ->and($pool->next())->toBe('http://5.45.126.128:8080')
            ->and($pool->next())->toBe('socks5://149.62.186.244:1080');
    } finally {
        unlink($path);
    }
});

test('proxy pool refresh replaces its list with valid downloaded endpoints', function () {
    Http::fake([
        'https://proxy-list.test/data.txt' => Http::response(implode(PHP_EOL, [
            'socks5://149.62.186.244:1080',
            'invalid',
            'http://5.45.126.128:8080',
        ])),
        'https://www.olx.ua/robots.txt' => Http::response('User-agent: *', 200),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'olx-proxies-');
    file_put_contents($path, 'http://old.proxy:8080');

    try {
        $pool = new OlxProxyPool(path: $path, sourceUrl: 'https://proxy-list.test/data.txt');

        expect($pool->refresh())->toBe(2)
            ->and(file($path, FILE_IGNORE_NEW_LINES))->toBe([
                'socks5://149.62.186.244:1080',
                'http://5.45.126.128:8080',
            ]);

        Http::assertSentCount(3);
    } finally {
        unlink($path);
    }
});

test('proxy pool refresh retains only proxies that can reach OLX', function () {
    Http::fake([
        'https://proxy-list.test/data.txt' => Http::response(implode(PHP_EOL, [
            'socks5://149.62.186.244:1080',
            'http://5.45.126.128:8080',
        ])),
        'https://www.olx.ua/robots.txt' => Http::sequence()
            ->push('User-agent: *', 200)
            ->push('blocked', 403),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'olx-proxies-');
    file_put_contents($path, 'http://old.proxy:8080');

    try {
        $pool = new OlxProxyPool(path: $path, sourceUrl: 'https://proxy-list.test/data.txt');

        expect($pool->refresh())->toBe(1)
            ->and(file($path, FILE_IGNORE_NEW_LINES))->toBe([
                'socks5://149.62.186.244:1080',
            ]);
    } finally {
        unlink($path);
    }
});

test('proxy pool refresh preserves the existing list when no proxy passes validation', function () {
    Http::fake([
        'https://proxy-list.test/data.txt' => Http::response('http://5.45.126.128:8080'),
        'https://www.olx.ua/robots.txt' => Http::response('blocked', 403),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'olx-proxies-');
    file_put_contents($path, 'http://old.proxy:8080');

    try {
        $pool = new OlxProxyPool(path: $path, sourceUrl: 'https://proxy-list.test/data.txt');

        expect($pool->refresh())->toBe(0)
            ->and(file($path, FILE_IGNORE_NEW_LINES))->toBe([
                'http://old.proxy:8080',
            ]);
    } finally {
        unlink($path);
    }
});

test('proxy pool refreshes a missing list before selecting a proxy', function () {
    Http::fake([
        'https://proxy-list.test/data.txt' => Http::response('http://5.45.126.128:8080'),
        'https://www.olx.ua/robots.txt' => Http::response('User-agent: *', 200),
    ]);

    $path = sys_get_temp_dir().'/olx-proxies-'.uniqid().'.txt';

    try {
        $pool = new OlxProxyPool(path: $path, sourceUrl: 'https://proxy-list.test/data.txt');

        expect($pool->refreshIfStale())->toBe(1)
            ->and($pool->next())->toBe('http://5.45.126.128:8080');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('refresh proxy command downloads the public list', function () {
    Http::fake([
        '*' => Http::response('http://5.45.126.128:8080'),
    ]);

    $path = storage_path('app/private/olx-proxies.txt');
    $originalContents = is_file($path) ? file_get_contents($path) : null;

    try {
        $this->artisan('olx:refresh-proxies')
            ->expectsOutput('Refreshed 1 OLX proxies.')
            ->assertExitCode(0);
    } finally {
        if ($originalContents === null && is_file($path)) {
            unlink($path);
        } elseif ($originalContents !== null) {
            file_put_contents($path, $originalContents);
        }
    }
});

test('refresh proxy command keeps a current proxy list', function () {
    Http::fake([
        '*' => Http::response('http://other.proxy:8080'),
    ]);

    $path = storage_path('app/private/olx-proxies.txt');
    $originalContents = is_file($path) ? file_get_contents($path) : null;
    file_put_contents($path, 'http://5.45.126.128:8080');

    try {
        $this->artisan('olx:refresh-proxies')
            ->expectsOutput('OLX proxy list is current.')
            ->assertExitCode(0);
    } finally {
        if ($originalContents === null) {
            unlink($path);
        } else {
            file_put_contents($path, $originalContents);
        }
    }
});
