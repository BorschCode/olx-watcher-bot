<?php

namespace Database\Seeders;

use App\Models\FilterOption;
use Illuminate\Database\Seeder;

class FilterOptionSeeder extends Seeder
{
    public function run(): void
    {
        $options = [
            // ── Стан оголошення ──────────────────────────────────────────
            [
                'group' => 'condition',
                'label' => 'Стан: Новий',
                'key' => 'filter_enum_condition',
                'value' => 'new',
                'has_range' => false,
            ],
            [
                'group' => 'condition',
                'label' => 'Стан: Вживаний',
                'key' => 'filter_enum_condition',
                'value' => 'used',
                'has_range' => false,
            ],

            // ── Ціна ─────────────────────────────────────────────────────
            [
                'group' => 'price',
                'label' => 'Ціна від/до',
                'key' => 'filter_float_price',
                'value' => null,
                'has_range' => true,
            ],

            // ── Валюта ───────────────────────────────────────────────────
            [
                'group' => 'currency',
                'label' => 'Валюта: Гривня (₴ UAH)',
                'key' => 'currency',
                'value' => 'UAH',
                'has_range' => false,
            ],
            [
                'group' => 'currency',
                'label' => 'Валюта: Долар ($ USD)',
                'key' => 'currency',
                'value' => 'USD',
                'has_range' => false,
            ],

            // ── Локація (OLX внутрішні ID, задаються для кожного watcher) ─
            [
                'group' => 'location',
                'label' => 'Категорія OLX (category_id)',
                'key' => 'category_id',
                'value' => null,
                'has_range' => false,
            ],
            [
                'group' => 'location',
                'label' => 'Регіон (OLX region_id)',
                'key' => 'region_id',
                'value' => null,
                'has_range' => false,
            ],
            [
                'group' => 'location',
                'label' => 'Місто OLX (city_id)',
                'key' => 'city_id',
                'value' => null,
                'has_range' => false,
            ],
            [
                'group' => 'location',
                'label' => 'Відстань від міста, км (distance)',
                'key' => 'distance',
                'value' => null,
                'has_range' => false,
            ],

            // ── Сортування ───────────────────────────────────────────────
            [
                'group' => 'other',
                'label' => 'Сортування: Нові спочатку',
                'key' => 'sort_by',
                'value' => 'created_at:desc',
                'has_range' => false,
            ],
            [
                'group' => 'other',
                'label' => 'Сортування: Дешевші спочатку',
                'key' => 'sort_by',
                'value' => 'filter_float_price:asc',
                'has_range' => false,
            ],
            [
                'group' => 'other',
                'label' => 'Сортування: Дорожчі спочатку',
                'key' => 'sort_by',
                'value' => 'filter_float_price:desc',
                'has_range' => false,
            ],
        ];

        foreach ($options as $option) {
            FilterOption::updateOrCreate(
                ['group' => $option['group'], 'key' => $option['key'], 'value' => $option['value']],
                $option,
            );
        }
    }
}
