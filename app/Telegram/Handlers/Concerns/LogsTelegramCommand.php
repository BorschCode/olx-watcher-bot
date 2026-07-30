<?php

namespace App\Telegram\Handlers\Concerns;

use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use Throwable;

trait LogsTelegramCommand
{
    protected function logCommandStart(Nutgram $bot, string $command): void
    {
        Log::info("Telegram command /{$command} invoked", [
            'command' => $command,
            'chat_id' => $bot->chatId(),
            'user_id' => $bot->userId(),
            'chat_type' => $bot->chat()?->type?->value,
            'text' => $bot->message()?->text,
        ]);
    }

    /**
     * @param  callable(): void  $callback
     */
    protected function runLoggedCommand(Nutgram $bot, string $command, callable $callback): void
    {
        $this->logCommandStart($bot, $command);

        try {
            $callback();

            Log::info("Telegram command /{$command} completed", [
                'command' => $command,
                'chat_id' => $bot->chatId(),
            ]);
        } catch (Throwable $exception) {
            Log::error("Telegram command /{$command} failed", [
                'command' => $command,
                'chat_id' => $bot->chatId(),
                'exception' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
