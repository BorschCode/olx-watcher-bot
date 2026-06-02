<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;

class MyIdHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $chatId = $bot->chatId();
        $userId = $bot->userId();
        $chatType = $bot->chat()?->type ?? 'unknown';

        $lines = [
            '🆔 <b>Ідентифікатори чату</b>',
            '',
            "💬 Chat ID: <code>{$chatId}</code>  ({$chatType})",
        ];

        if ($userId && $userId !== $chatId) {
            $lines[] = "👤 User ID: <code>{$userId}</code>";
        }

        $lines[] = '';
        $lines[] = '<i>Chat ID використовується для фільтрації збережених оголошень (/saved).</i>';

        $bot->sendMessage(
            text: implode("\n", $lines),
            parse_mode: 'HTML',
        );
    }
}
