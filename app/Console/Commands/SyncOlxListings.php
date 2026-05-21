<?php

namespace App\Console\Commands;

use App\Enums\HttpMethod;
use App\Models\Listing;
use App\Models\Watcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nutgram\Laravel\Facades\Telegram;
use SergiX44\Nutgram\Telegram\Types\Input\InputMediaPhoto;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SyncOlxListings extends Command
{
    protected $signature = 'olx:sync {--watcher= : Sync only a specific watcher ID}';

    protected $description = 'Fetch new OLX listings for all watchers and send Telegram notifications';

    private const int LIMIT = 40;

    private const string GRAPHQL_ENDPOINT = 'https://www.olx.ua/apigateway/graphql';

    private const string GRAPHQL_QUERY = <<<'GRAPHQL'
query ListingSearchQuery(
  $searchParameters: [SearchParameter!] = []
  $fetchPayAndShip: Boolean = false
) {
  clientCompatibleListings(searchParameters: $searchParameters) {
    __typename
    ... on ListingSuccess {
      __typename
      data {
        _nodeId
        id
        location {
          city { id name normalized_name }
          district { id name }
          region { id name normalized_name }
        }
        created_time
        last_refresh_time
        photos { link height width }
        title
        status
        url
        business
        params {
          key
          name
          type
          value {
            __typename
            ... on GenericParam { key label }
            ... on CheckboxesParam { label checkboxParamKey: key }
            ... on PriceParam {
              value type negotiable label currency
              converted_value converted_currency
            }
          }
        }
        description
        contact { name phone chat }
        promotion { highlighted top_ad urgent }
        protect_phone
        user { id name seller_type }
      }
      metadata {
        total_elements
        search_id
      }
      links {
        next { href }
      }
    }
    ... on ListingError {
      __typename
      error { code title detail status }
    }
  }
}
GRAPHQL;

    public function handle(): int
    {
        $query = Watcher::active()->with(['filterOptions', 'category', 'city']);

        if ($watcherId = $this->option('watcher')) {
            $query->where('id', $watcherId);
        }

        $watchers = $query->get();

        if ($watchers->isEmpty()) {
            $this->info('No watchers configured.');

            return self::SUCCESS;
        }

        Log::info('olx:sync started', [
            'watcher_count' => $watchers->count(),
            'watcher_ids' => $watchers->pluck('id')->all(),
        ]);

        foreach ($watchers as $watcher) {
            $this->syncWatcher($watcher);
        }

        Log::info('olx:sync finished', ['watcher_count' => $watchers->count()]);

        return self::SUCCESS;
    }

    private function syncWatcher(Watcher $watcher): void
    {
        $label = "Watcher #{$watcher->id}".($watcher->category ? " – {$watcher->category->name}" : '');
        $this->info("Syncing: {$label}");

        Log::info('Watcher sync started', [
            'watcher_id' => $watcher->id,
            'method' => $watcher->method->value,
            'last_seen_id' => $watcher->last_seen_id,
            'url' => $watcher->url,
        ]);

        $offers = match ($watcher->method) {
            HttpMethod::Get => $this->fetchViaRest($watcher),
            HttpMethod::GetHtml => $this->fetchViaHtmlLd($watcher),
            HttpMethod::Post => $this->fetchViaGraphql($watcher),
        };

        if ($offers === null) {
            Log::warning('Watcher fetch returned no data', ['watcher_id' => $watcher->id]);

            return;
        }

        Log::info('Watcher fetch completed', [
            'watcher_id' => $watcher->id,
            'fetched' => count($offers),
            'ids' => array_column($offers, 'id'),
        ]);

        if ($watcher->method === HttpMethod::GetHtml) {
            // OLX already filters server-side via min_id; every returned offer is new.
            // After processing, advance min_id in the stored URL to the highest seen listing ID.
            $newOffers = $offers;

            if ($newOffers !== [] && $watcher->url !== null) {
                $maxId = max(array_map(fn ($o) => (int) $o['id'], $newOffers));
                if ($maxId > 0) {
                    $watcher->update(['url' => $this->updateUrlMinId($watcher->url, $maxId)]);
                    Log::info('Watcher min_id advanced', [
                        'watcher_id' => $watcher->id,
                        'new_min_id' => $maxId,
                    ]);
                }
            }
        } else {
            $newOffers = [];
            $latestId = null;

            foreach ($offers as $offer) {
                $olxId = (int) $offer['id'];

                if ($watcher->last_seen_id && $olxId <= $watcher->last_seen_id) {
                    break;
                }

                $latestId ??= $olxId;
                $newOffers[] = $offer;
            }

            if ($latestId !== null) {
                $watcher->update(['last_seen_id' => $latestId]);
                Log::info('Watcher last_seen_id advanced', [
                    'watcher_id' => $watcher->id,
                    'previous_last_seen_id' => $watcher->last_seen_id,
                    'new_last_seen_id' => $latestId,
                    'new_offer_ids' => array_column($newOffers, 'id'),
                ]);
            } else {
                Log::info('Watcher no new offers', [
                    'watcher_id' => $watcher->id,
                    'last_seen_id' => $watcher->last_seen_id,
                    'fetched' => count($offers),
                ]);
            }
        }

        $notified = 0;

        foreach ($newOffers as $offer) {
            try {
                $this->sendNotification($watcher, $offer);
                $notified++;
                Log::info('Offer notified', ['watcher_id' => $watcher->id, 'offer_id' => $offer['id']]);
            } catch (\Throwable $e) {
                Log::error('Telegram notification failed', [
                    'watcher_id' => $watcher->id,
                    'offer_id' => $offer['id'],
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  Notification failed for offer #{$offer['id']}: {$e->getMessage()}");
            }

            sleep(1);
        }

        $total = count($newOffers);
        $this->line("  Done. {$notified}/{$total} notified.");

        Log::info('Watcher sync finished', [
            'watcher_id' => $watcher->id,
            'notified' => $notified,
            'total_new' => $total,
        ]);
    }

    /** @return array<int, array<string, mixed>>|null */
    private function fetchViaRest(Watcher $watcher): ?array
    {
        $baseUrl = $watcher->final_url;

        if ($baseUrl === null) {
            $this->warn('  No URL configured, skipping.');

            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $url = $baseUrl.$separator.http_build_query(['offset' => 0, 'limit' => self::LIMIT]);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'uk-UA,uk;q=0.9',
            'Referer' => 'https://www.olx.ua/',
            'Origin' => 'https://www.olx.ua',
        ])->get($url);

        if (! $response->successful()) {
            Log::error('OLX REST API error', ['watcher' => $watcher->id, 'status' => $response->status()]);
            $this->error("  REST API error {$response->status()}");

            return null;
        }

        return $response->json('data', []);
    }

    /** @return array<int, array<string, mixed>>|null */
    private function fetchViaGraphql(Watcher $watcher): ?array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Language' => 'uk-UA,uk;q=0.9',
            'Origin' => 'https://www.olx.ua',
            'Referer' => 'https://www.olx.ua/',
        ])->post(self::GRAPHQL_ENDPOINT, [
            'query' => self::GRAPHQL_QUERY,
            'variables' => [
                'searchParameters' => $watcher->buildSearchParameters(offset: 0, limit: self::LIMIT),
                'fetchPayAndShip' => false,
            ],
        ]);

        if (! $response->successful()) {
            Log::error('OLX GraphQL HTTP error', ['watcher' => $watcher->id, 'status' => $response->status()]);
            $this->error("  GraphQL HTTP error {$response->status()}");

            return null;
        }

        $result = $response->json('data.clientCompatibleListings');

        if (($result['__typename'] ?? null) === 'ListingError') {
            $error = $result['error'] ?? [];
            Log::error('OLX GraphQL listing error', ['watcher' => $watcher->id, 'error' => $error]);
            $this->error("  GraphQL error [{$error['code']}]: {$error['title']}");

            return null;
        }

        return $result['data'] ?? [];
    }

    /** @return array<int, array<string, mixed>>|null */
    private function fetchViaHtmlLd(Watcher $watcher): ?array
    {
        if ($watcher->url === null) {
            $this->warn('  No URL configured, skipping.');

            return null;
        }

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'uk-UA,uk;q=0.9',
            'Referer' => 'https://www.olx.ua/',
        ])->get($watcher->url);

        if (! $response->successful()) {
            Log::error('OLX HTML fetch error', ['watcher' => $watcher->id, 'status' => $response->status()]);
            $this->error("  HTML fetch error {$response->status()}");

            return null;
        }

        $html = $response->body();

        // Primary: window.__PRERENDERED_STATE__ – real integer IDs + full data
        $ads = $this->parsePrerenderedAds($html);
        if ($ads !== null) {
            return $ads;
        }

        // Fallback: LD+JSON Product schema
        preg_match_all(
            '/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/s',
            $html,
            $matches,
        );

        foreach ($matches[1] as $jsonStr) {
            $data = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE || ($data['@type'] ?? null) !== 'Product') {
                continue;
            }

            $ldOffers = $data['offers']['offers'] ?? [];

            if ($ldOffers === []) {
                continue;
            }

            return array_values(array_map(fn (array $offer): array => [
                'id' => $this->decodeOlxIdFromUrl($offer['url'] ?? ''),
                'title' => $offer['name'] ?? '',
                'url' => $offer['url'] ?? '',
                'price_valid_until' => $offer['priceValidUntil'] ?? null,
                'params' => [
                    ['key' => 'price', 'value' => ['value' => $offer['price'] ?? 0]],
                ],
                'photos' => array_map(
                    fn (string $img) => ['link' => $img],
                    $offer['image'] ?? [],
                ),
            ], $ldOffers));
        }

        $this->warn('  No usable data found in HTML response.');

        return null;
    }

    /** @return array<int, array<string, mixed>>|null */
    private function parsePrerenderedAds(string $html): ?array
    {
        $marker = 'window.__PRERENDERED_STATE__ = ';
        $markerPos = strpos($html, $marker);

        if ($markerPos === false || ($html[$markerPos + strlen($marker)] ?? '') !== '"') {
            return null;
        }

        // Scan past the opening quote, respecting backslash escapes, to find the closing quote
        $i = $markerPos + strlen($marker) + 1;
        $len = strlen($html);
        while ($i < $len) {
            $c = $html[$i];
            if ($c === '\\') {
                $i += 2;
            } elseif ($c === '"') {
                break;
            } else {
                $i++;
            }
        }

        $jsonStringLiteral = substr($html, $markerPos + strlen($marker), $i - $markerPos - strlen($marker) + 1);
        $innerJson = json_decode($jsonStringLiteral);

        if (! is_string($innerJson)) {
            return null;
        }

        $data = json_decode($innerJson, true);
        $ads = $data['listing']['listing']['ads'] ?? null;

        if (! is_array($ads) || $ads === []) {
            return null;
        }

        return array_values(array_map(fn (array $ad): array => [
            'id' => (int) ($ad['id'] ?? 0),
            'title' => $ad['title'] ?? '',
            'url' => $ad['url'] ?? '',
            'created_time' => $ad['createdTime'] ?? null,
            'last_refresh_time' => $ad['lastRefreshTime'] ?? null,
            'price_valid_until' => $ad['validToTime'] ?? null,
            'params' => isset($ad['price']['regularPrice']['value'])
                ? [['key' => 'price', 'value' => ['value' => $ad['price']['regularPrice']['value']]]]
                : [],
            'photos' => array_map(
                fn (string $url) => ['link' => $url],
                $ad['photos'] ?? [],
            ),
        ], $ads));
    }

    private function decodeOlxIdFromUrl(string $url): int
    {
        if (! preg_match('/ID([A-Za-z0-9]+)\.html$/', $url, $m)) {
            return 0;
        }

        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = 0;

        foreach (str_split($m[1]) as $char) {
            $pos = strpos($chars, $char);
            if ($pos === false) {
                return 0;
            }
            $result = $result * 62 + $pos;
        }

        return $result;
    }

    private function updateUrlMinId(string $url, int $minId): string
    {
        if (str_contains($url, 'min_id=')) {
            return (string) preg_replace('/min_id=\d+/', "min_id={$minId}", $url);
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator."min_id={$minId}";
    }

    /** @param array<string, mixed> $offer */
    private function sendNotification(Watcher $watcher, array $offer): void
    {
        $price = Listing::extractPrice($offer);
        $images = Listing::extractImages($offer);

        $validUntilRaw = $offer['price_valid_until'] ?? null;
        $validUntil = $this->formatOfferDate($validUntilRaw);

        $createdAtRaw = $offer['created_time'] ?? null;
        if ($createdAtRaw === null && $validUntilRaw !== null) {
            try {
                $createdAtRaw = Carbon::parse((string) $validUntilRaw)->subDays(30);
            } catch (\Throwable) {
            }
        }
        $createdAt = $this->formatOfferDate($createdAtRaw);
        $refreshedAt = $this->formatOfferDate($offer['last_refresh_time'] ?? null);

        $caption = implode("\n", array_filter([
            "🆕 <b>{$offer['title']}</b>",
            $price ? '💰 '.number_format($price, 0, '.', ' ').' грн' : null,
            $createdAt ? "📅 Опубліковано: {$createdAt}" : null,
            $refreshedAt && $refreshedAt !== $createdAt ? "🔄 Оновлено: {$refreshedAt}" : null,
            $validUntil ? "⏰ Активно до: {$validUntil}" : null,
            "🔗 {$offer['url']}",
        ]));

        $replyMarkup = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make('💾 Зберегти на потім', callback_data: "save_{$offer['id']}"),
        );

        if (count($images) > 1) {
            $media = array_map(
                fn (string $url) => InputMediaPhoto::make(media: $url),
                array_slice($images, 0, 10),
            );

            $this->sendWithRetry(fn () => Telegram::sendMediaGroup(
                media: $media,
                chat_id: $watcher->telegram_chat_id,
            ));

            $this->sendWithRetry(fn () => Telegram::sendMessage(
                text: $caption,
                parse_mode: 'HTML',
                reply_markup: $replyMarkup,
                chat_id: $watcher->telegram_chat_id,
            ));
        } elseif ($images !== []) {
            $this->sendWithRetry(fn () => Telegram::sendPhoto(
                photo: $images[0],
                caption: $caption,
                parse_mode: 'HTML',
                reply_markup: $replyMarkup,
                chat_id: $watcher->telegram_chat_id,
            ));
        } else {
            $this->sendWithRetry(fn () => Telegram::sendMessage(
                text: $caption,
                parse_mode: 'HTML',
                reply_markup: $replyMarkup,
                chat_id: $watcher->telegram_chat_id,
            ));
        }
    }

    private function sendWithRetry(callable $send, int $maxRetries = 3): void
    {
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $send();

                return;
            } catch (\Throwable $e) {
                if ($attempt === $maxRetries) {
                    throw $e;
                }

                if (preg_match('/retry after (\d+)/i', $e->getMessage(), $m)) {
                    $wait = (int) $m[1] + 1;
                    $this->line("    Rate limited, retrying in {$wait}s…");
                    sleep($wait);
                } elseif (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                    $this->line('    Request timed out, retrying…');
                    sleep(3);
                } else {
                    throw $e;
                }
            }
        }
    }

    private function formatOfferDate(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        try {
            if ($date instanceof Carbon) {
                return $date->format('d.m.Y H:i');
            }

            $carbon = is_numeric($date)
                ? Carbon::createFromTimestamp((int) $date)
                : Carbon::parse((string) $date);

            return $carbon->format('d.m.Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
