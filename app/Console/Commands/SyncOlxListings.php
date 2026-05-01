<?php

namespace App\Console\Commands;

use App\Enums\HttpMethod;
use App\Models\Listing;
use App\Models\Watcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nutgram\Laravel\Facades\Telegram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SyncOlxListings extends Command
{
    protected $signature = 'olx:sync {--watcher= : Sync only a specific watcher ID}';

    protected $description = 'Fetch new OLX listings for all watchers and send Telegram notifications';

    private const LIMIT = 40;

    private const CACHE_TTL_DAYS = 7;

    public function handle(): int
    {
        $query = Watcher::with('filterOptions', 'category');

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

            Cache::put("olx_offer_{$olxId}", [
                'offer' => $offer,
                'category_id' => $watcher->category_id,
            ], now()->addDays(self::CACHE_TTL_DAYS));

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
            'Accept-Language' => 'uk-UA,uk;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Referer' => 'https://www.olx.ua/',
            'Origin' => 'https://www.olx.ua',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ])->get($url);

        if (! $response->successful()) {
            Log::error('OLX REST API error', ['watcher' => $watcher->id, 'status' => $response->status()]);
            $this->error("  REST API error {$response->status()} body: {$response->body()}");

            return null;
        }

        return $response->json('data', []);
    }

    /** @return array<int, array<string, mixed>>|null */
    private function fetchViaGraphql(Watcher $watcher): ?array
    {
        if ($watcher->url === null || $watcher->request_body === null) {
            $this->warn('  No URL or request body configured, skipping.');

            return null;
        }

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($watcher->url, $watcher->request_body);

        if (! $response->successful()) {
            Log::error('OLX GraphQL error', ['watcher' => $watcher->id, 'status' => $response->status()]);
            $this->error("  GraphQL error {$response->status()}");

            return null;
        }

        return $response->json('data.clientCompatibleObservedAds.data', []);
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
