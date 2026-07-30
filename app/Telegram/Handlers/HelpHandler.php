<?php

namespace App\Telegram\Handlers;

use App\Support\AppUrl;
use SergiX44\Nutgram\Nutgram;

class HelpHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $appUrl = AppUrl::base();
        $watchersUrl = AppUrl::adminWatchers();

        $text = implode("\n", [
            '👋 <b>OLX Watcher Bot</b>',
            '',
            'Цей бот стежить за оголошеннями на OLX та надсилає сповіщення про нові.',
            '',
            '<b>Команди:</b>',
            '/saved — переглянути останні 10 збережених оголошень',
            '/myid — показати ваш Chat ID та User ID',
            '/help — показати цю довідку',
            '',
            '<b>Керування:</b>',
            "🌐 Додаток: <a href=\"{$appUrl}\">{$appUrl}</a>",
            "⚙️ Спостерігачі: <a href=\"{$watchersUrl}\">{$watchersUrl}</a>",
            '',
            '<b>Як зберегти оголошення?</b>',
            'Коли бот надсилає сповіщення, натисніть кнопку <i>💾 Зберегти на потім</i> під повідомленням.',
        ]);

        $bot->sendMessage(
            text: $text,
            parse_mode: 'HTML',
        );
    }
}
