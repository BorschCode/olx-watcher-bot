<?php

namespace App\Telegram;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotUsernameResolver
{
    private const CACHE_KEY = 'telegram.bot_username';

    public static function resolve(bool $force = false): void
    {
        if (filled(config('nutgram.config.bot_name'))) {
            return;
        }

        $token = config('nutgram.token');

        if (! filled($token) || (app()->runningUnitTests() && ! $force)) {
            return;
        }

        try {
            $username = Cache::rememberForever(self::CACHE_KEY, function () use ($token): ?string {
                $response = Http::timeout(10)
                    ->connectTimeout(5)
                    ->get("https://api.telegram.org/bot{$token}/getMe");

                if (! $response->successful()) {
                    Log::warning('Failed to resolve Telegram bot username from getMe', [
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                return $response->json('result.username');
            });
        } catch (Throwable $exception) {
            Log::warning('Failed to resolve Telegram bot username from getMe', [
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (filled($username)) {
            config(['nutgram.config.bot_name' => $username]);

            Log::info('Telegram bot username resolved automatically', [
                'bot_username' => $username,
            ]);
        }
    }
}
