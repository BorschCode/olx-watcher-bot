<?php

namespace App\Telegram\Handlers;

use App\Models\SavedAd;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class SavedAdsHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $chatId = (string) $bot->chatId();
        $adminChatId = (string) config('nutgram.admin_chat_id', '');
        $isAdmin = $adminChatId !== '' && $chatId === $adminChatId;

        Log::info('SavedAdsHandler invoked', [
            'chat_id' => $chatId,
            'admin_chat_id' => $adminChatId,
            'is_admin' => $isAdmin,
            'total_saved_ads' => SavedAd::count(),
        ]);

        $query = SavedAd::query()->orderByDesc('created_at')->limit(10);

        if (! $isAdmin) {
            $query->where('telegram_chat_id', $chatId);
        }

        $ads = $query->get();

        Log::info('SavedAdsHandler query result', [
            'count' => $ads->count(),
            'chat_ids_in_db' => SavedAd::distinct()->pluck('telegram_chat_id')->all(),
        ]);

        if ($ads->isEmpty()) {
            $text = $isAdmin
                ? '📭 Збережених оголошень немає (жоден користувач ще не зберіг).'
                : '📭 У вас ще немає збережених оголошень.';

            $bot->sendMessage(text: $text, parse_mode: 'HTML');

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
