<?php

namespace App\Console\Commands;

use App\Enums\HttpMethod;
use App\Models\Listing;
use App\Models\Watcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nutgram\Laravel\Facades\Telegram;
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
        $query = Watcher::with(['filterOptions', 'category', 'city']);

        if ($watcherId = $this->option('watcher')) {
            $query->where('id', $watcherId);
        }

        $watchers = $query->get();

        if ($watchers->isEmpty()) {
            $this->info('No watchers configured.');

            return self::SUCCESS;
        }

        foreach ($watchers as $watcher) {
            $this->syncWatcher($watcher);
        }

        return self::SUCCESS;
    }

    private function syncWatcher(Watcher $watcher): void
    {
        $label = "Watcher #{$watcher->id}".($watcher->category ? " – {$watcher->category->name}" : '');
        $this->info("Syncing: {$label}");

        $offers = match ($watcher->method) {
            HttpMethod::Get => $this->fetchViaRest($watcher),
            HttpMethod::Post => $this->fetchViaGraphql($watcher),
        };

        if ($offers === null) {
            return;
        }

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
        }

        $notified = 0;

        foreach ($newOffers as $offer) {
            try {
                $this->sendNotification($watcher, $offer);
                $notified++;
            } catch (\Throwable $e) {
                Log::error('Telegram notification failed', [
                    'watcher' => $watcher->id,
                    'offer' => $offer['id'],
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  Notification failed for offer #{$offer['id']}: {$e->getMessage()}");
            }
        }

        $total = count($newOffers);
        $this->line("  Done. {$notified}/{$total} notified.");
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

    /** @param array<string, mixed> $offer */
    private function sendNotification(Watcher $watcher, array $offer): void
    {
        $price = Listing::extractPrice($offer);
        $images = Listing::extractImages($offer);

        $caption = implode("\n", array_filter([
            "🆕 <b>{$offer['title']}</b>",
            $price ? '💰 '.number_format($price, 0, '.', ' ').' грн' : null,
            "🔗 {$offer['url']}",
        ]));

        $replyMarkup = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make('💾 Зберегти на потім', callback_data: "save_{$offer['id']}"),
        );

        if ($images !== []) {
            Telegram::sendPhoto(
                photo: $images[0],
                caption: $caption,
                parse_mode: 'HTML',
                reply_markup: $replyMarkup,
                chat_id: $watcher->telegram_chat_id,
            );
        } else {
            Telegram::sendMessage(
                text: $caption,
                parse_mode: 'HTML',
                reply_markup: $replyMarkup,
                chat_id: $watcher->telegram_chat_id,
            );
        }
    }
}
