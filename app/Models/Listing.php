<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $url
 * @property int|null $price
 * @property string[]|null $images
 * @property CarbonImmutable $parsed_at
 * @property int|null $category_id
 * @property-read Category|null $category
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing whereParsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing whereUrl($value)
 *
 * @mixin \Eloquent
 */
class Listing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'title',
        'url',
        'price',
        'images',
        'parsed_at',
    ];

    protected $casts = [
        'parsed_at' => 'datetime',
        'images' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /** @param array<string, mixed> $offer */
    public static function extractPrice(array $offer): ?int
    {
        foreach ($offer['params'] ?? [] as $param) {
            if ($param['key'] === 'price') {
                $value = $param['value']['converted_value'] ?? $param['value']['value'] ?? 0;

                return (int) $value ?: null;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $offer */
    public static function extractRooms(array $offer): ?string
    {
        return self::extractParamDisplayValue($offer, 'number_of_rooms')
            ?? self::extractParamDisplayValue($offer, 'number_of_rooms_string');
    }

    /** @param array<string, mixed> $offer */
    public static function extractTotalArea(array $offer): ?string
    {
        return self::extractParamDisplayValue($offer, 'total_area');
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public static function extractParamDisplayValue(array $offer, string $key): ?string
    {
        foreach ($offer['params'] ?? [] as $param) {
            if (($param['key'] ?? null) !== $key) {
                continue;
            }

            $value = $param['value'] ?? null;

            if (is_string($value)) {
                return $value !== '' ? $value : null;
            }

            if (is_array($value)) {
                $label = $value['label'] ?? null;

                if (is_string($label) && $label !== '') {
                    return $label;
                }

                if (isset($value['key'])) {
                    return (string) $value['key'];
                }
            }

            return null;
        }

        return null;
    }

    /** @return string[] */
    public static function extractImages(array $offer): array
    {
        return array_values(array_map(
            fn (array $photo) => str_replace('{width}x{height}', '644x461', $photo['link']),
            array_filter($offer['photos'] ?? [], fn ($p) => isset($p['link'])),
        ));
    }
}
