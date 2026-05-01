<?php

namespace App\Console\Commands;

use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportOlxLocations extends Command
{
    protected $signature = 'olx:import-locations';

    protected $description = 'Import OLX cities from the locations sitemap';

    public function handle(): int
    {
        $xml = Http::get('https://www.olx.ua/sitemap-locations.xml')->body();

        $sitemap = simplexml_load_string($xml);

        if ($sitemap === false) {
            $this->error('Failed to parse sitemap XML.');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($sitemap->url as $entry) {
            $url = (string) $entry->loc;

            // Each entry is like https://www.olx.ua/kyiv/
            // Skip entries that are not top-level city URLs
            $path = trim(parse_url($url, PHP_URL_PATH), '/');

            if (str_contains($path, '/')) {
                $skipped++;

                continue;
            }

            $slug = $path;
            $name = ucfirst($slug);

            City::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );

            $this->line("Imported: {$slug}");
            $imported++;
        }

        $this->info("Done. {$imported} cities imported, {$skipped} entries skipped.");

        return self::SUCCESS;
    }
}
