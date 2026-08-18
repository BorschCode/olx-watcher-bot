<?php

namespace App\Support;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OlxProxyPool
{
    private const string SOURCE_URL = 'https://cdn.jsdelivr.net/gh/proxyscrape/free-proxy-list@main/proxies/all/data.txt';

    private const int REFRESH_INTERVAL_SECONDS = 86400;

    private const string VALIDATION_URL = 'https://www.olx.ua/robots.txt';

    private const int VALIDATION_TIMEOUT_SECONDS = 5;

    private const int VALIDATION_CONNECT_TIMEOUT_SECONDS = 3;

    private const int VALIDATION_CONCURRENCY = 25;

    /** @var list<string>|null */
    private ?array $proxies = null;

    private int $position = 0;

    public function __construct(
        private ?string $path = null,
        private string $sourceUrl = self::SOURCE_URL,
    ) {
        $this->path ??= storage_path('app/private/olx-proxies.txt');
    }

    public function next(): ?string
    {
        $proxies = $this->all();

        if ($proxies === []) {
            return null;
        }

        $proxy = $proxies[$this->position % count($proxies)];
        $this->position++;

        return $proxy;
    }

    /** @return list<string> */
    public function all(): array
    {
        if ($this->proxies !== null) {
            return $this->proxies;
        }

        if (! is_file($this->path)) {
            return $this->proxies = [];
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $this->proxies = $this->parse($lines === false ? [] : $lines);
    }

    public function refresh(): int
    {
        $response = Http::timeout(20)->get($this->sourceUrl);

        if (! $response->successful()) {
            return 0;
        }

        $proxies = $this->validate($this->parse(preg_split('/\R/', $response->body()) ?: []));

        if ($proxies === []) {
            return 0;
        }

        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $temporaryPath = $this->path.'.tmp';
        file_put_contents($temporaryPath, implode(PHP_EOL, $proxies).PHP_EOL);
        rename($temporaryPath, $this->path);

        $this->proxies = $proxies;
        $this->position = 0;

        return count($proxies);
    }

    /**
     * @param  list<string>  $proxies
     * @return list<string>
     */
    private function validate(array $proxies): array
    {
        if ($proxies === []) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($proxies): array {
            return array_map(
                fn (string $proxy) => $pool
                    ->as($proxy)
                    ->timeout(self::VALIDATION_TIMEOUT_SECONDS)
                    ->connectTimeout(self::VALIDATION_CONNECT_TIMEOUT_SECONDS)
                    ->withOptions([
                        'proxy' => $proxy,
                        'verify' => $this->proxyVerify(),
                    ])
                    ->get(self::VALIDATION_URL),
                $proxies,
            );
        }, self::VALIDATION_CONCURRENCY);

        return array_values(array_filter(
            $proxies,
            fn (string $proxy): bool => ($responses[$proxy] ?? null) instanceof Response
                && $responses[$proxy]->successful(),
        ));
    }

    public function refreshIfStale(): int
    {
        $lastModified = is_file($this->path) ? filemtime($this->path) : false;

        if ($lastModified !== false && $lastModified >= now()->subSeconds(self::REFRESH_INTERVAL_SECONDS)->getTimestamp()) {
            return 0;
        }

        return $this->refresh();
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function parse(array $lines): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', $lines),
            fn (string $proxy): bool => $this->isValid($proxy),
        )));
    }

    private function isValid(string $proxy): bool
    {
        $parts = parse_url($proxy);

        return $parts !== false
            && in_array($parts['scheme'] ?? null, ['http', 'socks4', 'socks5'], true)
            && isset($parts['host'], $parts['port']);
    }

    private function proxyVerify(): bool
    {
        return (bool) config('services.olx.proxy_verify', true);
    }
}
