<?php

namespace App\Telegram;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotUsernameResolver
{
    private const CACHE_KEY = 'telegram.bot_username';

    public static function resolve(): void
    {
        if (filled(config('nutgram.config.bot_name'))) {
            return;
        }

        $token = config('nutgram.token');

        if (! filled($token) || app()->runningUnitTests()) {
            return;
        }

        $username = Cache::rememberForever(self::CACHE_KEY, function () use ($token): ?string {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            if (! $response->successful()) {
                Log::warning('Failed to resolve Telegram bot username from getMe', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json('result.username');
        });

        if (filled($username)) {
            config(['nutgram.config.bot_name' => $username]);

            Log::info('Telegram bot username resolved automatically', [
                'bot_username' => $username,
            ]);
        }
    }
}
