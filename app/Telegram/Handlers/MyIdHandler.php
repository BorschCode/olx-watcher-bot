<?php

namespace App\Telegram\Handlers;

use App\Telegram\Handlers\Concerns\LogsTelegramCommand;
use SergiX44\Nutgram\Nutgram;

class MyIdHandler
{
    use LogsTelegramCommand;

    public function __invoke(Nutgram $bot): void
    {
        $this->runLoggedCommand($bot, 'myid', function () use ($bot): void {
            $chatId = $bot->chatId();
            $userId = $bot->userId();
            $chatType = $bot->chat()?->type ?? 'unknown';

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
        });
    }
}
