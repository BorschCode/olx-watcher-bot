<?php

namespace App\Console\Commands;

use App\Support\OlxProxyPool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('olx:refresh-proxies')]
#[Description('Refresh the public OLX proxy list')]
class RefreshOlxProxies extends Command
{
    public function handle(OlxProxyPool $proxyPool): int
    {
        $proxyCount = $proxyPool->refreshIfStale();

        if ($proxyCount === 0) {
            if ($proxyPool->all() !== []) {
                $this->info('OLX proxy list is current.');

                return self::SUCCESS;
            }

            $this->error('No valid OLX proxies were downloaded.');

            return self::FAILURE;
        }

        $this->info("Refreshed {$proxyCount} OLX proxies.");

        return self::SUCCESS;
    }
}
