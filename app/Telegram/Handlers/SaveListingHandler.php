<?php

namespace App\Telegram\Handlers;

use App\Models\SavedAd;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SaveListingHandler
{
    public function __invoke(Nutgram $bot, string $olxId): void
    {
        $cached = Cache::get("olx_offer_{$olxId}");

        if ($cached === null) {
            $bot->answerCallbackQuery(text: '⌛ Термін зберігання минув.');

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
