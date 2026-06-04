<?php

namespace App\Telegram\Handlers;

use App\Models\SavedAd;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SaveAutoRiaListingHandler
{
    public function __invoke(Nutgram $bot, string $riaId): void
    {
        $cached = Cache::get("autoria_offer_{$riaId}");

        if ($cached === null) {
            $bot->answerCallbackQuery(text: '⌛ Дані оголошення недоступні.');

            return;
        }

        $info = $cached['info'];
        $chatId = $cached['telegram_chat_id'];

        $savedAd = SavedAd::firstOrCreate(
            [
                'olx_id' => (int) $riaId,
                'source' => 'auto_ria',
                'telegram_chat_id' => $chatId,
            ],
            [
                'title' => $cached['title'],
                'url' => $info['linkToView'] ?? '',
                'price' => $cached['price_usd'],
                'images' => $cached['images'] ?: null,
            ],
        );

        if ($savedAd->wasRecentlyCreated) {
            Log::info('Auto.ria ad saved by user', [
                'ria_id' => $riaId,
                'telegram_chat_id' => $chatId,
                'saved_ad_id' => $savedAd->id,
            ]);

            $bot->answerCallbackQuery(text: '✅ Збережено!');
        } else {
            $bot->answerCallbackQuery(text: '📌 Вже збережено раніше.');
        }

        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());
    }
}
