<?php

namespace App\Console\Commands;

use App\Models\Watcher;
use Illuminate\Console\Command;
use Nutgram\Laravel\Facades\Telegram;

class TestWatcherNotification extends Command
{
    protected $signature = 'olx:test-notify {--watcher= : Send only to a specific watcher ID}';

    protected $description = 'Send a test Telegram notification to one or all watcher chat IDs';

    public function handle(): int
    {
        $query = Watcher::with('category');

        if ($watcherId = $this->option('watcher')) {
            $query->where('id', $watcherId);
        }

        $watchers = $query->get();

        if ($watchers->isEmpty()) {
            $this->warn('No watchers found.');

            return self::FAILURE;
        }

        foreach ($watchers as $watcher) {
            $label = "Watcher #{$watcher->id}".($watcher->category ? " – {$watcher->category->name}" : '');

            Telegram::sendMessage(
                text: implode("\n", [
                    '🔔 <b>Тестове повідомлення</b>',
                    "Спостерігач: <b>{$label}</b>",
                    "Chat ID: <code>{$watcher->telegram_chat_id}</code>",
                    '',
                    '✅ Бот працює та надсилає сповіщення.',
                ]),
                parse_mode: 'HTML',
                chat_id: $watcher->telegram_chat_id,
            );

            $this->info("Sent to {$label} (chat: {$watcher->telegram_chat_id})");
        }

        return self::SUCCESS;
    }
}
