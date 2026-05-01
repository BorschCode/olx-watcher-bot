<?php

namespace App\Console\Commands;

use App\Enums\HttpMethod;
use App\Models\Listing;
use App\Models\Watcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncOlxListings extends Command
{
    protected $signature = 'olx:sync {--watcher= : Sync only a specific watcher ID}';

    protected $description = 'Fetch new OLX listings for all watchers and send Telegram notifications';

    private const LIMIT = 40;

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

        $newCount = 0;
        $latestId = null;

        foreach ($offers as $offer) {
            $olxId = (int) $offer['id'];

            if ($watcher->last_seen_id && $olxId <= $watcher->last_seen_id) {
                break;
            }

            $latestId ??= $olxId;

            $listing = Listing::firstOrCreate(
                ['url' => $offer['url']],
                [
                    'category_id' => $watcher->category_id,
                    'title' => $offer['title'],
                    'price' => $this->extractPrice($offer),
                    'parsed_at' => now(),
                ],
            );

            if ($listing->wasRecentlyCreated) {
                $this->sendNotification($watcher, $listing);
                $newCount++;
            }
        }

        if ($latestId !== null) {
            $watcher->update(['last_seen_id' => $latestId]);
        }

        $this->line("  Done. {$newCount} new listing(s).");
    }

    /** @return array<int, array<string, mixed>>|null */
    private function fetchViaRest(Watcher $watcher): ?array
    {
        if ($watcher->url === null) {
            $this->warn('  No URL configured, skipping.');

            return null;
        }

        $params = array_merge(
            $watcher->filterParams(),
            ['offset' => 0, 'limit' => self::LIMIT],
        );

        $response = Http::get($watcher->url, $params);

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
    private function extractPrice(array $offer): ?int
    {
        foreach ($offer['params'] ?? [] as $param) {
            if ($param['key'] === 'price') {
                $value = $param['value']['converted_value'] ?? $param['value']['value'] ?? 0;

                return (int) $value ?: null;
            }
        }

        return null;
    }

    private function sendNotification(Watcher $watcher, Listing $listing): void
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            $this->warn('  TELEGRAM_BOT_TOKEN not set, skipping notification.');

            return;
        }

        $lines = array_filter([
            "🆕 <b>{$listing->title}</b>",
            $listing->price ? '💰 '.number_format($listing->price, 0, '.', ' ').' грн' : null,
            "🔗 {$listing->url}",
        ]);

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $watcher->telegram_chat_id,
            'text' => implode("\n", $lines),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
        ]);
    }
}
