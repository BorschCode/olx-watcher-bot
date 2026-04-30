<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Category;

#[Signature('app:import-olx-categories')]
#[Description('Command description')]
class ImportOlxCategories extends Command
{
    protected $signature = 'olx:import-categories';

    protected $description = 'Import OLX categories from sitemap';

    public function handle()
    {
        $xml = Http::get(
            'https://www.olx.ua/sitemap-categories.xml'
        )->body();

        $sitemap = simplexml_load_string($xml);

        foreach ($sitemap->url as $entry) {

            $url = (string)$entry->loc;

            $slug = trim(parse_url($url, PHP_URL_PATH), '/');

            Category::updateOrCreate(
                ['url' => $url],
                [
                    'name' => ucfirst(last(explode('/', $slug))),
                    'slug' => $slug
                ]
            );

            $this->info("Imported: $slug");
        }

        return Command::SUCCESS;
    }
}
