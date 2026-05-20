<?php

namespace App\Console\Commands;

use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportOlxLocations extends Command
{
    protected $signature = 'olx:import-locations';

    protected $description = 'Import OLX cities (with OLX IDs and region IDs) from the OLX API';

    private const string CITIES_API = 'https://www.olx.ua/api/v1/cities/';

    private const int PAGE_SIZE = 200;

    public function handle(): int
    {
        $offset = 0;
        $imported = 0;
        $updated = 0;

        do {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ])->get(self::CITIES_API, [
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
            ]);

            if (! $response->successful()) {
                $this->error("API error {$response->status()} at offset {$offset}");

                return self::FAILURE;
            }

            $cities = $response->json('data', []);

            if (empty($cities)) {
                break;
            }

            foreach ($cities as $city) {
                $olxId = (int) $city['id'];
                $regionId = (int) $city['region_id'];
                $slug = $city['normalized_name'] ?? '';
                $name = $city['name'] ?? ucfirst($slug);

                $existing = City::where('olx_id', $olxId)->first()
                    ?? ($slug ? City::where('slug', $slug)->first() : null);

                if ($existing) {
                    $existing->update([
                        'olx_id' => $olxId,
                        'region_id' => $regionId,
                        'slug' => $slug,
                        'name' => $name,
                    ]);
                    $updated++;
                } else {
                    City::create([
                        'olx_id' => $olxId,
                        'region_id' => $regionId,
                        'slug' => $slug,
                        'name' => $name,
                    ]);
                    $imported++;
                }
            }

            $this->line("  Processed offset {$offset}: ".count($cities).' cities');

            $offset += self::PAGE_SIZE;
            $hasMore = $response->json('links.next') !== null;

        } while ($hasMore);

        $this->info("Done. {$imported} new, {$updated} updated.");

        return self::SUCCESS;
    }
}
