<?php

/** @var Nutgram $bot */

use App\Telegram\Handlers\HelpHandler;
use App\Telegram\Handlers\SavedAdsHandler;
use App\Telegram\Handlers\SaveListingHandler;
use SergiX44\Nutgram\Nutgram;

$bot->onCommand('start', HelpHandler::class);
$bot->onCommand('help', HelpHandler::class);
$bot->onCommand('saved', SavedAdsHandler::class);

$bot->onCallbackQueryData('save_{olxId}', SaveListingHandler::class);
