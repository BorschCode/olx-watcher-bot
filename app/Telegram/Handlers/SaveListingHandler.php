<?php

namespace App\Telegram\Handlers;

use App\Models\Listing;
use App\Models\SavedAd;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SaveListingHandler
{
    public function __invoke(Nutgram $bot, string $olxId): void
    {
        Log::info('SaveListingHandler invoked', [
            'olx_id' => $olxId,
            'chat_id' => $bot->chatId(),
            'user_id' => $bot->userId(),
            'callback_data' => $bot->currentUpdate()?->callback_query?->data,
        ]);

        $cached = Cache::get("olx_offer_{$olxId}")
            ?? $this->fetchFromOlxApi((int) $olxId, (string) $bot->chatId());

        if ($cached === null) {
            $bot->answerCallbackQuery(text: '⌛ Дані оголошення недоступні.');

            return;
        }

        $offer = $cached['offer'];
        $chatId = $cached['telegram_chat_id'];

        $savedAd = SavedAd::firstOrCreate(
            [
                'olx_id' => (int) $olxId,
                'telegram_chat_id' => $chatId,
            ],
            [
                'title' => $offer['title'] ?? '',
                'url' => $offer['url'] ?? '',
                'price' => $cached['price'],
                'images' => $cached['images'] ?: null,
                'published_at' => $this->parseDate($offer['created_time'] ?? null),
                'refreshed_at' => $this->parseDate($offer['last_refresh_time'] ?? null),
                'valid_until' => $this->parseDate($offer['price_valid_until'] ?? $offer['validToTime'] ?? null),
            ],
        );

        if ($savedAd->wasRecentlyCreated) {
            Log::info('Ad saved by user', [
                'olx_id' => $olxId,
                'telegram_chat_id' => $chatId,
                'saved_ad_id' => $savedAd->id,
            ]);

            $bot->answerCallbackQuery(text: '✅ Збережено!');
        } else {
            $bot->answerCallbackQuery(text: '📌 Вже збережено раніше.');
        }

        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());
    }

    /** @return array<string, mixed>|null */
    private function fetchFromOlxApi(int $olxId, string $chatId): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'application/json',
                'Accept-Language' => 'uk-UA,uk;q=0.9',
                'Referer' => 'https://www.olx.ua/',
            ])->get("https://www.olx.ua/api/v1/offers/{$olxId}");

            if (! $response->successful()) {
                return null;
            }

            $offer = $response->json('data');

            if (! is_array($offer) || empty($offer)) {
                return null;
            }

            return [
                'offer' => $offer,
                'telegram_chat_id' => $chatId,
                'price' => Listing::extractPrice($offer),
                'images' => Listing::extractImages($offer),
            ];
        } catch (\Throwable $e) {
            Log::warning('OLX API fallback failed in SaveListingHandler', [
                'olx_id' => $olxId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parseDate(mixed $date): ?Carbon
    {
        if ($date === null) {
            return null;
        }

        try {
            return is_numeric($date)
                ? Carbon::createFromTimestamp((int) $date)
                : Carbon::parse((string) $date);
        } catch (\Throwable) {
            return null;
        }
    }
}
