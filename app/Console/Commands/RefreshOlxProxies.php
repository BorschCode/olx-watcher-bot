<?php

namespace App\Console\Commands;

use App\Support\OlxProxyPool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('olx:refresh-proxies {--force : Validate and replace the current proxy list immediately}')]
#[Description('Refresh the public OLX proxy list')]
class RefreshOlxProxies extends Command
{
    public function handle(OlxProxyPool $proxyPool): int
    {
        try {
            $proxyCount = $this->option('force')
                ? $proxyPool->refresh()
                : $proxyPool->refreshIfStale();
        } catch (Throwable $exception) {
            report($exception);
            $this->error("Failed to refresh OLX proxies: {$exception->getMessage()}");
            $proxyCount = 0;
        }

        if ($proxyCount > 0) {
            $this->info("Refreshed {$proxyCount} OLX proxies.");

            return self::SUCCESS;
        }

        $existing = count($proxyPool->all());

        if ($existing > 0) {
            if ($this->option('force')) {
                $label = $existing === 1 ? 'proxy' : 'proxies';
                $this->warn("Kept {$existing} existing OLX {$label}.");
            } else {
                $this->info('OLX proxy list is current.');
            }

            return self::SUCCESS;
        }

        $this->error('No valid OLX proxies were downloaded.');

        return self::FAILURE;
    }
}
