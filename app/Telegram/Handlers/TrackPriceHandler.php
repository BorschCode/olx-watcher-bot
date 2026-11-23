<?php

namespace App\Telegram\Handlers;

use App\Models\SavedAd;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class TrackPriceHandler
{
    public function __invoke(Nutgram $bot, string $id): void
    {
        $chatId = (string) $bot->chatId();

        Log::info('TrackPriceHandler invoked', [
            'saved_ad_id' => $id,
            'chat_id' => $chatId,
            'callback_data' => $bot->callbackQuery()?->data,
        ]);

        $savedAd = SavedAd::where('id', $id)->first();

        if ($savedAd === null) {
            $bot->answerCallbackQuery(text: '❌ Оголошення не знайдено.');

            return;
        }

        $savedAd->update(['track_price' => ! $savedAd->track_price]);

        $tracking = $savedAd->track_price;

        Log::info('Price tracking toggled', [
            'saved_ad_id' => $savedAd->id,
            'telegram_chat_id' => $chatId,
            'track_price' => $tracking,
        ]);

        $bot->answerCallbackQuery(
            text: $tracking ? '✅ Відстеження ціни увімкнуто' : '⏹ Відстеження ціни вимкнено',
        );
    }
}
