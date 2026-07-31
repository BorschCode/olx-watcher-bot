<?php

namespace App\Telegram\Middleware;

use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

class LogTelegramUpdate
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $update = $bot->update();
        $message = $bot->message();
        $updateId = isset($update->update_id) ? $update->update_id : null;

        Log::info('Telegram update received', [
            'update_id' => $updateId,
            'update_type' => $update?->getType()?->value,
            'chat_id' => $bot->chatId(),
            'user_id' => $bot->userId(),
            'chat_type' => $bot->chat()?->type?->value,
            'text' => $message?->text,
            'callback_data' => $bot->callbackQuery()?->data,
        ]);

        try {
            $next($bot);

            Log::info('Telegram update handled', [
                'update_id' => $updateId,
            ]);
        } catch (Throwable $exception) {
            Log::error('Telegram update handler failed', [
                'update_id' => $updateId,
                'exception' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
