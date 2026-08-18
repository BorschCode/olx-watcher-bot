<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OlxHttpClient
{
    private const int CHROME_VERSION_MIN = 120;

    private const int CHROME_VERSION_MAX = 131;

    private ?string $lastProxy = null;

    /** @var list<string> */
    private const ACCEPT_LANGUAGES = [
        'uk-UA,uk;q=0.9,en-US;q=0.8,en;q=0.7',
        'uk-UA,uk;q=0.9',
        'uk,en-US;q=0.9,en;q=0.8',
        'uk-UA,uk;q=0.8,en;q=0.6',
    ];

    /** @var list<array{platform: string, sec_ch_ua_platform: string, user_agent_template: string}> */
    private const CHROME_PROFILES = [
        [
            'platform' => 'Windows',
            'sec_ch_ua_platform' => '"Windows"',
            'user_agent_template' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/%d.0.0.0 Safari/537.36',
        ],
        [
            'platform' => 'macOS',
            'sec_ch_ua_platform' => '"macOS"',
            'user_agent_template' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/%d.0.0.0 Safari/537.36',
        ],
        [
            'platform' => 'Linux',
            'sec_ch_ua_platform' => '"Linux"',
            'user_agent_template' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/%d.0.0.0 Safari/537.36',
        ],
    ];

    /** @var list<array{platform: string, user_agent_template: string}> */
    private const FIREFOX_PROFILES = [
        [
            'platform' => 'Windows',
            'user_agent_template' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:%d.0) Gecko/20100101 Firefox/%d.0',
        ],
        [
            'platform' => 'macOS',
            'user_agent_template' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:%d.0) Gecko/20100101 Firefox/%d.0',
        ],
    ];

    public function __construct(
        private ?string $proxy = null,
        private bool $proxyVerify = true,
        private int $maxRetries = 3,
        private ?OlxProxyPool $proxyPool = null,
    ) {
        $this->proxy ??= config('services.olx.proxy');
        $this->proxyVerify = (bool) config('services.olx.proxy_verify', $this->proxyVerify);
        $this->maxRetries = (int) config('services.olx.fetch_retries', $this->maxRetries);
        $this->proxyPool ??= new OlxProxyPool;
    }

    /**
     * @return array{id: string, browser: string, platform: string, headers: array<string, string>}
     */
    public function randomFingerprint(string $context = 'html'): array
    {
        $useChrome = random_int(0, 1) === 1;
        $acceptLanguage = self::ACCEPT_LANGUAGES[array_rand(self::ACCEPT_LANGUAGES)];

        if ($useChrome) {
            $profile = self::CHROME_PROFILES[array_rand(self::CHROME_PROFILES)];
            $chromeVersion = random_int(self::CHROME_VERSION_MIN, self::CHROME_VERSION_MAX);
            $userAgent = sprintf($profile['user_agent_template'], $chromeVersion);

            $headers = array_merge($this->baseHeaders($context, $acceptLanguage), [
                'User-Agent' => $userAgent,
                'sec-ch-ua' => sprintf(
                    '"Chromium";v="%d", "Google Chrome";v="%d", "Not-A.Brand";v="99"',
                    $chromeVersion,
                    $chromeVersion,
                ),
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => $profile['sec_ch_ua_platform'],
            ]);

            return [
                'id' => $this->fingerprintId($userAgent),
                'browser' => "Chrome {$chromeVersion}",
                'platform' => $profile['platform'],
                'headers' => $headers,
            ];
        }

        $profile = self::FIREFOX_PROFILES[array_rand(self::FIREFOX_PROFILES)];
        $firefoxVersion = random_int(120, 132);
        $userAgent = sprintf($profile['user_agent_template'], $firefoxVersion, $firefoxVersion);

        $headers = array_merge($this->baseHeaders($context, $acceptLanguage), [
            'User-Agent' => $userAgent,
        ]);

        return [
            'id' => $this->fingerprintId($userAgent),
            'browser' => "Firefox {$firefoxVersion}",
            'platform' => $profile['platform'],
            'headers' => $headers,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function client(array $headers): PendingRequest
    {
        $request = Http::timeout((int) config('services.olx.timeout', 20))
            ->connectTimeout((int) config('services.olx.connect_timeout', 10))
            ->withHeaders($headers);

        $proxy = $this->proxyPool->next() ?? $this->proxy;
        $this->lastProxy = $proxy;

        if ($proxy !== null && $proxy !== '') {
            $request = $request->withOptions([
                'proxy' => $proxy,
                'verify' => $this->proxyVerify,
            ]);
        }

        return $request;
    }

    public function get(string $url, string $context = 'html', ?int $watcherId = null, string $logKey = 'OLX fetch error'): Response
    {
        return $this->request('GET', $url, context: $context, watcherId: $watcherId, logKey: $logKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $url, array $payload, string $context = 'json', ?int $watcherId = null, string $logKey = 'OLX fetch error'): Response
    {
        return $this->request('POST', $url, payload: $payload, context: $context, watcherId: $watcherId, logKey: $logKey);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function request(
        string $method,
        string $url,
        ?array $payload = null,
        string $context = 'html',
        ?int $watcherId = null,
        string $logKey = 'OLX fetch error',
    ): Response {
        $response = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $fingerprint = $this->randomFingerprint($context);
            $request = $this->client($fingerprint['headers']);

            $response = $method === 'POST'
                ? $request->post($url, $payload ?? [])
                : $request->get($url);

            if ($response->successful()) {
                if ($attempt > 1) {
                    Log::info('OLX fetch succeeded after retry', [
                        'watcher' => $watcherId,
                        'url' => $url,
                        'attempt' => $attempt,
                        'fingerprint' => $this->fingerprintSummary($fingerprint),
                    ]);
                }

                return $response;
            }

            $logContext = $this->buildLogContext($response, $fingerprint, $url, $watcherId, $attempt);

            if ($this->shouldRetry($response, $attempt)) {
                Log::warning('OLX fetch blocked, retrying with new fingerprint', $logContext);
                sleep($attempt * 2);

                continue;
            }

            Log::error($logKey, $logContext);

            return $response;
        }

        return $response ?? Http::response('', 500);
    }

    /**
     * @param  array{id: string, browser: string, platform: string, headers: array<string, string>}  $fingerprint
     * @return array<string, mixed>
     */
    public function buildLogContext(
        Response $response,
        array $fingerprint,
        string $url,
        ?int $watcherId,
        int $attempt,
    ): array {
        $body = $response->body();
        $responseHeaders = collect($response->headers())
            ->only([
                'server',
                'cf-ray',
                'cf-cache-status',
                'x-cache',
                'x-amz-cf-id',
                'content-type',
                'retry-after',
                'x-block-reason',
                'x-akamai-transformed',
            ])
            ->map(fn (array $values): string => implode(', ', $values))
            ->all();

        return [
            'watcher' => $watcherId,
            'url' => $url,
            'status' => $response->status(),
            'attempt' => $attempt,
            'max_retries' => $this->maxRetries,
            'fingerprint' => $this->fingerprintSummary($fingerprint),
            'request_headers' => $this->safeRequestHeaders($fingerprint['headers']),
            'response_headers' => $responseHeaders,
            'response_body_preview' => Str::squish(Str::limit($body, 500)),
            'response_body_length' => strlen($body),
            'blocked_hint' => $this->detectBlockReason($body, $responseHeaders),
            'proxy_configured' => $this->lastProxy !== null && $this->lastProxy !== '',
            'proxy' => $this->proxyIdentifier($this->lastProxy),
        ];
    }

    /**
     * @param  array{id: string, browser: string, platform: string, headers: array<string, string>}  $fingerprint
     * @return array<string, string>
     */
    private function fingerprintSummary(array $fingerprint): array
    {
        return [
            'id' => $fingerprint['id'],
            'browser' => $fingerprint['browser'],
            'platform' => $fingerprint['platform'],
            'user_agent' => $fingerprint['headers']['User-Agent'],
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function safeRequestHeaders(array $headers): array
    {
        return collect($headers)
            ->except(['Cookie'])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function baseHeaders(string $context, string $acceptLanguage): array
    {
        $headers = [
            'Accept-Language' => $acceptLanguage,
            'Referer' => 'https://www.olx.ua/',
            'Origin' => 'https://www.olx.ua',
            'DNT' => '1',
            'Connection' => 'keep-alive',
        ];

        if ($context === 'json') {
            return array_merge($headers, [
                'Accept' => 'application/json, text/plain, */*',
                'sec-fetch-dest' => 'empty',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-site' => 'same-origin',
            ]);
        }

        if ($context === 'graphql') {
            return array_merge($headers, [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'sec-fetch-dest' => 'empty',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-site' => 'same-origin',
            ]);
        }

        return array_merge($headers, [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Upgrade-Insecure-Requests' => '1',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-user' => '?1',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function fingerprintId(string $userAgent): string
    {
        return substr(hash('xxh128', $userAgent.microtime(true).random_int(0, PHP_INT_MAX)), 0, 12);
    }

    private function proxyIdentifier(?string $proxy): ?string
    {
        if ($proxy === null || $proxy === '') {
            return null;
        }

        $parts = parse_url($proxy);
        if ($parts === false || ! isset($parts['host'], $parts['port'])) {
            return null;
        }

        return "{$parts['host']}:{$parts['port']}";
    }

    private function shouldRetry(Response $response, int $attempt): bool
    {
        if ($attempt >= $this->maxRetries) {
            return false;
        }

        return in_array($response->status(), [403, 429, 503], true);
    }

    /**
     * @param  array<string, string>  $responseHeaders
     */
    private function detectBlockReason(string $body, array $responseHeaders): ?string
    {
        $haystack = Str::lower($body.' '.implode(' ', $responseHeaders));

        return match (true) {
            str_contains($haystack, 'cloudflare') || isset($responseHeaders['cf-ray']) => 'cloudflare',
            str_contains($haystack, 'captcha') || str_contains($haystack, 'recaptcha') => 'captcha',
            str_contains($haystack, 'akamai') || isset($responseHeaders['x-akamai-transformed']) => 'akamai',
            str_contains($haystack, 'access denied') => 'access_denied',
            str_contains($haystack, 'bot') && str_contains($haystack, 'detect') => 'bot_detection',
            default => null,
        };
    }
}
