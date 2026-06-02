<?php

namespace App\Telegram\Handlers;

use App\Models\SavedAd;
use SergiX44\Nutgram\Nutgram;

class SavedAdsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $chatId = (string) $bot->chatId();
        $adminChatId = (string) config('nutgram.admin_chat_id', '');
        $isAdmin = $adminChatId !== '' && $chatId === $adminChatId;

        $query = SavedAd::query()->orderByDesc('created_at')->limit(10);

        if (! $isAdmin) {
            $query->where('telegram_chat_id', $chatId);
        }

        $ads = $query->get();

        if ($ads->isEmpty()) {
            $bot->sendMessage(
                text: '📭 У вас ще немає збережених оголошень.',
                parse_mode: 'HTML',
            );

            return;
        }

        $lines = [
            $isAdmin
                ? '👑 <b>Усі збережені оголошення (адмін):</b>'
                : '📌 <b>Останні збережені оголошення:</b>',
            '',
        ];

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
