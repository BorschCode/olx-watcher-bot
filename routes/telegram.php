<?php

/** @var Nutgram $bot */

use App\Telegram\Handlers\HelpHandler;
use App\Telegram\Handlers\MyIdHandler;
use App\Telegram\Handlers\SaveAutoRiaListingHandler;
use App\Telegram\Handlers\SavedAdsHandler;
use App\Telegram\Handlers\SaveListingHandler;
use SergiX44\Nutgram\Nutgram;

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
