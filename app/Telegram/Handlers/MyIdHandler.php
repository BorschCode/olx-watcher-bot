<?php

namespace App\Telegram\Handlers;

use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class MyIdHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $chatId = $bot->chatId();
        $userId = $bot->userId();
        $chatType = $bot->chat()?->type ?? 'unknown';

        Log::info('MyIdHandler invoked', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'chat_type' => $chatType,
            'update_type' => $bot->currentUpdate()?->getType(),
        ]);

        $lines = [
            '🆔 <b>Ідентифікатори чату</b>',
            '',
            "💬 Chat ID: <code>{$chatId}</code>  ({$chatType})",
            "👤 User ID: <code>{$userId}</code>",
            '',
            '<i>Chat ID використовується для фільтрації збережених оголошень (/saved).</i>',
        ];

        $bot->sendMessage(
            text: implode("\n", $lines),
            parse_mode: 'HTML',
        );

        Log::info('MyIdHandler message sent', ['chat_id' => $chatId]);
    }
}
