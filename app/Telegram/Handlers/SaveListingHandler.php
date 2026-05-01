<?php

namespace App\Telegram\Handlers;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;
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

        $listing = Listing::firstOrCreate(
            ['url' => $offer['url']],
            [
                'category_id' => $cached['category_id'],
                'title' => $offer['title'],
                'price' => Listing::extractPrice($offer),
                'images' => Listing::extractImages($offer) ?: null,
                'parsed_at' => now(),
            ],
        );

        if ($listing->wasRecentlyCreated) {
            $bot->answerCallbackQuery(text: '✅ Збережено!');
        } else {
            $bot->answerCallbackQuery(text: '📌 Вже збережено раніше.');
        }

        $bot->editMessageReplyMarkup(reply_markup: InlineKeyboardMarkup::make());
    }
}
