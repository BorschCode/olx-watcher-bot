<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncOlxListings extends Command
{
    protected $signature = 'olx:sync';

    protected $description = 'Fetch new OLX listings for synced categories and notify Telegram subscribers';

    private const LIMIT = 40;

    private const OLX_API_BASE = 'https://www.olx.ua/api/v1/offers';

    public function handle(): int
    {
        $categories = Category::where('sync', true)->get();

        if ($categories->isEmpty()) {
            $this->info('No categories marked for sync.');

            return self::SUCCESS;
        }

        foreach ($categories as $category) {
            $this->syncCategory($category);
        }

        return self::SUCCESS;
    }

    private function syncCategory(Category $category): void
    {
        $this->info("Syncing: {$category->name}");

        $subscriptions = Subscription::where('category_id', $category->id)->get();

        if ($subscriptions->isEmpty()) {
            $this->line('  No subscribers, skipping.');

            return;
        }

        $offset = 0;
        $newCount = 0;

        do {
            $offers = $this->fetchOffers($category, $offset);

            if ($offers === null) {
                break;
            }

            $foundExisting = false;

            foreach ($offers as $offer) {
                if (Listing::where('url', $offer['url'])->exists()) {
                    $foundExisting = true;
                    break;
                }

                $listing = Listing::create([
                    'category_id' => $category->id,
                    'title' => $offer['title'],
                    'url' => $offer['url'],
                    'price' => $this->extractPrice($offer),
                    'parsed_at' => now(),
                ]);

                $this->sendToSubscribers($subscriptions, $listing);
                $newCount++;
            }

            $offset += self::LIMIT;

            // Stop paginating once we hit a known listing or got a partial page
        } while (! $foundExisting && count($offers) === self::LIMIT);

        $this->line("  Done. {$newCount} new listing(s).");
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchOffers(Category $category, int $offset): ?array
    {
        $params = array_merge(
            $category->options ?? [],
            ['offset' => $offset, 'limit' => self::LIMIT],
        );

        $response = Http::get(self::OLX_API_BASE, $params);

        if (! $response->successful()) {
            Log::error('OLX API error', [
                'category' => $category->id,
                'status' => $response->status(),
            ]);
            $this->error("  API error {$response->status()} for category {$category->name}");

            return null;
        }

        return $response->json('data', []);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private function extractPrice(array $offer): ?int
    {
        foreach ($offer['params'] ?? [] as $param) {
            if ($param['key'] === 'price') {
                return (int) ($param['value']['converted_value'] ?? $param['value']['value'] ?? 0) ?: null;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     */
    private function sendToSubscribers(Collection $subscriptions, Listing $listing): void
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            $this->warn('  TELEGRAM_BOT_TOKEN not set, skipping notifications.');

            return;
        }

        $text = implode("\n", [
            "🆕 <b>{$listing->title}</b>",
            $listing->price ? '💰 '.number_format($listing->price, 0, '.', ' ').' грн' : '',
            "🔗 {$listing->url}",
        ]);

        foreach ($subscriptions as $subscription) {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $subscription->telegram_chat_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
            ]);
        }
    }
}
