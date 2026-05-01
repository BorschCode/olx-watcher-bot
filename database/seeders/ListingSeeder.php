<?php

namespace Database\Seeders;

use App\Models\Listing;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    public function run(): void
    {
        $listings = [
            [
                'title' => '2-кімнатна квартира в центрі',
                'url' => 'https://www.olx.ua/uk/obyavlenie/2-kimnatna-kvartira-v-tsentri-IDfake1.html',
                'price' => 45000,
                'parsed_at' => now()->subHours(2),
            ],
            [
                'title' => 'Продам BMW 520d 2019',
                'url' => 'https://www.olx.ua/uk/obyavlenie/prodam-bmw-520d-2019-IDfake2.html',
                'price' => 28000,
                'parsed_at' => now()->subHours(3),
            ],
            [
                'title' => 'iPhone 15 Pro Max 256GB',
                'url' => 'https://www.olx.ua/uk/obyavlenie/iphone-15-pro-max-IDfake3.html',
                'price' => 1200,
                'parsed_at' => now()->subHours(1),
            ],
            [
                'title' => 'Диван кутовий новий',
                'url' => 'https://www.olx.ua/uk/obyavlenie/dyvan-kutovyi-IDfake4.html',
                'price' => 850,
                'parsed_at' => now()->subHours(5),
            ],
            [
                'title' => 'Щеня лабрадора',
                'url' => 'https://www.olx.ua/uk/obyavlenie/shchenia-labrador-IDfake5.html',
                'price' => 500,
                'parsed_at' => now()->subDay(),
            ],
        ];

        foreach ($listings as $listing) {
            Listing::firstOrCreate(['url' => $listing['url']], $listing);
        }
    }
}
