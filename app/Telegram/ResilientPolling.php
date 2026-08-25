<?php

namespace App\Telegram;

use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;
use SergiX44\Nutgram\Telegram\Types\Common\Update;
use Throwable;

class ResilientPolling extends Polling
{
    public static int $retryDelaySeconds = 5;

    public function processUpdates(Nutgram $bot): void
    {
        $this->listenForSignals();

        $config = $bot->getConfig();
        $offset = 1;

        echo "Listening...\n";
        while (self::$FOREVER) {
            try {
                $updates = $bot->getUpdates(
                    offset: $offset,
                    limit: $config->pollingLimit,
                    timeout: $config->pollingTimeout,
                    allowed_updates: $config->pollingAllowedUpdates
                ) ?? [];
            } catch (Throwable $exception) {
                Log::warning('Telegram polling request failed, retrying', [
                    'error' => $exception->getMessage(),
                ]);
                sleep(self::$retryDelaySeconds);

                continue;
            }

            if ($offset === 1) {
                /** @var Update|false $last */
                $last = end($updates);
                if ($last) {
                    $offset = $last->update_id;
                }

                continue;
            }

            $offset += count($updates);

            $this->fire($bot, $updates);
        }
    }
}
