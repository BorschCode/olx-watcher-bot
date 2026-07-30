<?php

namespace App\Telegram\Middleware;

use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

class LogTelegramUpdate
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $update = $bot->currentUpdate();
        $message = $update?->getMessage();

        Log::info('Telegram update received', [
            'update_id' => $update?->update_id,
            'update_type' => $update?->getType()?->value,
            'chat_id' => $bot->chatId(),
            'user_id' => $bot->userId(),
            'chat_type' => $bot->chat()?->type?->value,
            'text' => $message?->text,
            'callback_data' => $update?->callback_query?->data,
        ]);

        try {
            $next($bot);

            Log::info('Telegram update handled', [
                'update_id' => $update?->update_id,
            ]);
        } catch (Throwable $exception) {
            Log::error('Telegram update handler failed', [
                'update_id' => $update?->update_id,
                'exception' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
