<?php

/** @var Nutgram $bot */

use App\Telegram\Handlers\SaveListingHandler;
use SergiX44\Nutgram\Nutgram;

$bot->onCallbackQueryData('save_{olxId}', SaveListingHandler::class);
