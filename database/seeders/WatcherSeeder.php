<?php

namespace Database\Seeders;

use App\Enums\HttpMethod;
use App\Models\Category;
use App\Models\City;
use App\Models\FilterOption;
use App\Models\Watcher;
use Illuminate\Database\Seeder;

class WatcherSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRestWatcher();
        $this->seedGraphqlWatcher();
    }

    private function seedRestWatcher(): void
    {
        $category = Category::firstOrCreate(
            ['slug' => 'nerukhomist-orenda-budinki'],
            ['name' => 'Оренда: Будинки, дачі, котеджі'],
        );

        $city = City::firstOrCreate(
            ['slug' => 'kyiv'],
            ['name' => 'Київ', 'olx_id' => 268, 'region_id' => 25],
        );

        $watcher = Watcher::firstOrCreate(
            ['category_id' => $category->id, 'method' => HttpMethod::Get],
            [
                'telegram_chat_id' => env('TELEGRAM_CHAT_ID', '0'),
                'city_id' => $city->id,
                'url' => 'https://www.olx.ua/api/v1/offers',
                'last_seen_id' => null,
            ],
        );

        $watcher->filterOptions()->sync([
            FilterOption::firstWhere(['key' => 'category_id'])->id => ['value_from' => '330'],
            FilterOption::firstWhere(['key' => 'region_id'])->id => ['value_from' => '25'],
            FilterOption::firstWhere(['key' => 'city_id'])->id => ['value_from' => '268'],
            FilterOption::firstWhere(['key' => 'currency', 'value' => 'UAH'])->id => [],
            FilterOption::firstWhere(['key' => 'filter_float_price', 'has_range' => true])->id => ['value_to' => '30000'],
        ]);
    }

    /**
     * GraphQL (POST) watcher — apartment rentals in Kyiv.
     *
     * Produces searchParameters:
     *   category_id=1760, city_id=268, region_id=25,
     *   currency=UAH, sort_by=created_at:desc,
     *   filter_enum_number_of_rooms_string[0..2]=dvuhkomnatnye/trehkomnatnye/chetyrehkomnatnye,
     *   filter_float_price:to=22000
     */
    private function seedGraphqlWatcher(): void
    {
        $category = Category::firstOrCreate(
            ['slug' => 'nerukhomist/kvartiry-komnaty/orenda-kvartir'],
            ['name' => 'Оренда квартир', 'olx_id' => 1760],
        );

        $city = City::firstOrCreate(
            ['slug' => 'kyiv'],
            ['name' => 'Київ', 'olx_id' => 268, 'region_id' => 25],
        );

        // Ensure olx_id and region_id are set on Kyiv
        $city->update(['olx_id' => 268, 'region_id' => 25]);

        $watcher = Watcher::firstOrCreate(
            ['category_id' => $category->id, 'method' => HttpMethod::Post],
            [
                'telegram_chat_id' => env('TELEGRAM_CHAT_ID', '0'),
                'city_id' => $city->id,
                'url' => 'https://www.olx.ua/apigateway/graphql',
                'last_seen_id' => null,
            ],
        );

        $watcher->filterOptions()->sync([
            FilterOption::firstWhere(['key' => 'filter_enum_number_of_rooms_string', 'value' => 'dvuhkomnatnye'])->id => [],
            FilterOption::firstWhere(['key' => 'filter_enum_number_of_rooms_string', 'value' => 'trehkomnatnye'])->id => [],
            FilterOption::firstWhere(['key' => 'filter_enum_number_of_rooms_string', 'value' => 'chetyrehkomnatnye'])->id => [],
            FilterOption::firstWhere(['key' => 'filter_float_price', 'has_range' => true])->id => ['value_to' => '22000'],
        ]);
    }
}
