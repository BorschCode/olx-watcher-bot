<?php

/** @var Nutgram $bot */

use App\Telegram\Handlers\HelpHandler;
use App\Telegram\Handlers\MyIdHandler;
use App\Telegram\Handlers\SaveAutoRiaListingHandler;
use App\Telegram\Handlers\SavedAdsHandler;
use App\Telegram\Handlers\SaveListingHandler;
use App\Telegram\Middleware\LogTelegramUpdate;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

$bot->middleware(LogTelegramUpdate::class);

if (app()->runningInConsole()) {
    Log::info('Telegram bot handlers registered for polling', [
        'bot_username' => config('nutgram.config.bot_name'),
        'token_configured' => filled(config('nutgram.token')),
    ]);
}

$bot->onCommand('start', HelpHandler::class)
    ->description('Показати довідку');
$bot->onCommand('help', HelpHandler::class)
    ->description('Показати довідку');
$bot->onCommand('saved', SavedAdsHandler::class)
    ->description('Останні збережені оголошення');
$bot->onCommand('myid', MyIdHandler::class)
    ->description('Показати Chat ID та User ID');

$bot->onCallbackQueryData('save_{olxId}', SaveListingHandler::class);
$bot->onCallbackQueryData('save_ria_{riaId}', SaveAutoRiaListingHandler::class);

$bot->fallback(function (Nutgram $bot): void {
    Log::warning('Telegram update had no matching handler', [
        'update_id' => $bot->currentUpdate()?->update_id,
        'update_type' => $bot->currentUpdate()?->getType()?->value,
        'chat_id' => $bot->chatId(),
        'user_id' => $bot->userId(),
        'text' => $bot->message()?->text,
        'callback_data' => $bot->currentUpdate()?->callback_query?->data,
    ]);
});
