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
        $category = Category::firstOrCreate(
            ['slug' => 'nerukhomist-orenda-budinki'],
            ['name' => 'Оренда: Будинки, дачі, котеджі'],
        );

        $city = City::firstOrCreate(
            ['slug' => 'kyiv'],
            ['name' => 'Київ'],
        );

        $watcher = Watcher::firstOrCreate(
            ['category_id' => $category->id, 'method' => HttpMethod::Get],
            [
                'telegram_chat_id' => env('TELEGRAM_CHAT_ID', '0'),
                'city_id' => $city->id,
                'url' => 'https://www.olx.ua/api/v1/offers',
                'last_seen_id' => 922134293,
            ],
        );

        $watcher->filterOptions()->sync([
            FilterOption::firstWhere('key', 'category_id')->id => ['value_from' => '330'],
            FilterOption::firstWhere('key', 'region_id')->id => ['value_from' => '25'],
            FilterOption::firstWhere('key', 'city_id')->id => ['value_from' => '268'],
            FilterOption::firstWhere(['key' => 'currency', 'value' => 'UAH'])->id => [],
            FilterOption::firstWhere(['key' => 'filter_float_price', 'has_range' => true])->id => ['value_to' => '30000'],
        ]);
    }
}
