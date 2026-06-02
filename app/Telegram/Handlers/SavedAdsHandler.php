<?php

namespace App\Telegram\Handlers;

use App\Models\SavedAd;
use SergiX44\Nutgram\Nutgram;

class SavedAdsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $chatId = (string) $bot->chatId();

        $ads = SavedAd::query()
            ->where('telegram_chat_id', $chatId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($ads->isEmpty()) {
            $bot->sendMessage(
                text: '📭 У вас ще немає збережених оголошень.',
                parse_mode: 'HTML',
            );

            return;
        }

        $lines = ['📌 <b>Останні збережені оголошення:</b>', ''];

        foreach ($ads as $index => $ad) {
            $num = $index + 1;
            $price = $ad->price ? number_format($ad->price, 0, '.', ' ').' грн' : 'ціна невідома';
            $lines[] = "{$num}. <a href=\"{$ad->url}\">{$ad->title}</a> — {$price}";
        }

        $bot->sendMessage(
            text: implode("\n", $lines),
            parse_mode: 'HTML',
            disable_web_page_preview: true,
        );
    }
}
