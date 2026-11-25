<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\PriceHistory;
use App\Models\SavedAd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nutgram\Laravel\Facades\Telegram;

class TrackOlxPrices extends Command
{
    protected $signature = 'olx:track-prices {--dry-run : Do not persist changes, only log what would be done}';

    protected $description = 'Check tracked saved ads for price changes and record history';

    private const string OLX_OFFER_ENDPOINT = 'https://www.olx.ua/api/v1/offers/{id}';

    public function handle(): int
    {
        $tracked = SavedAd::all()->load('watcher')->filter(fn ($savedAd) => (bool) $savedAd->track_price);

        if ($tracked->isEmpty()) {
            $this->info('No tracked ads.');

            return self::SUCCESS;
        }

        $this->info("Checking {$tracked->count()} tracked ad(s) for price changes.");

        $changed = 0;
        $checked = 0;

        foreach ($tracked as $savedAd) {
            $checked++;

            $currentPrice = $this->fetchCurrentPrice($savedAd->olx_id);

            if ($currentPrice === null) {
                Log::warning('Price fetch failed, skipping', [
                    'saved_ad_id' => $savedAd->id,
                    'olx_id' => $savedAd->olx_id,
                ]);

                continue;
            }

            $previousPrice = $savedAd->price;

            if ($previousPrice === $currentPrice) {
                $this->line("  #{$savedAd->id} (olx {$savedAd->olx_id}): no change ({$currentPrice}).");

                continue;
            }

            if (! $this->option('dry-run')) {
                PriceHistory::create([
                    'saved_ad_id' => $savedAd->id,
                    'old_price' => $previousPrice,
                    'new_price' => $currentPrice,
                    'changed_at' => now(),
                ]);

                $savedAd->update(['price' => $currentPrice]);

                $this->notifyPriceChange($savedAd, $previousPrice, $currentPrice);
            }

            $changed++;

            $this->line(
                "  #{$savedAd->id} (olx {$savedAd->olx_id}): "
                .($previousPrice ? number_format($previousPrice, 0, '.', ' ') : '—')
                .' → '
                .number_format($currentPrice, 0, '.', ' ')
                .$this->currencySymbol($savedAd)
            );
        }

        $this->info("Done. {$checked} checked, {$changed} changed.");

        return self::SUCCESS;
    }

    /** @return string Currency symbol for the watched ad's URL (UAH → грн, USD → $, EUR → €). */
    private function currencySymbol(SavedAd $savedAd): string
    {
        $url = $savedAd->watcher?->url ?? $savedAd->url ?? '';

        if (preg_match('/[?&]currency=([^&]+)/i', $url, $m)) {
            return match (strtoupper((string) $m[1])) {
                'UAH' => 'грн',
                'USD' => '$',
                'EUR' => '€',
                default => $m[1],
            };
        }

        return 'грн';
    }

    private function fetchCurrentPrice(int $olxId): ?int
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Language' => 'uk-UA,uk;q=0.9',
                'Referer' => 'https://www.olx.ua/',
            ])->get(str_replace('{id}', (string) $olxId, self::OLX_OFFER_ENDPOINT));

            if (! $response->successful()) {
                return null;
            }

            $offer = $response->json('data');

            if (! is_array($offer) || empty($offer)) {
                return null;
            }

            return Listing::extractPrice($offer);
        } catch (\Throwable $e) {
            Log::warning('OLX price fetch failed', [
                'olx_id' => $olxId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function notifyPriceChange(SavedAd $savedAd, ?int $previousPrice, int $currentPrice): void
    {
        $symbol = $this->currencySymbol($savedAd);
        $old = $previousPrice ? number_format($previousPrice, 0, '.', ' ').$symbol : 'невідома';
        $new = number_format($currentPrice, 0, '.', ' ').$symbol;

        Telegram::sendMessage(
            text: implode("\n", array_filter([
                '💰 Зміна ціни у збереженому оголошенні',
                '<b>'.e($savedAd->title).'</b>',
                "{$old} → {$new}",
                '🔗 '.$savedAd->url,
            ])),
            parse_mode: 'HTML',
            chat_id: $savedAd->telegram_chat_id,
        );

        Log::info('Price change notified', [
            'saved_ad_id' => $savedAd->id,
            'olx_id' => $savedAd->olx_id,
            'old_price' => $previousPrice,
            'new_price' => $currentPrice,
        ]);
    }
}
