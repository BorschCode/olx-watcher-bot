<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $url
 * @property int|null $price
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
        'parsed_at',
    ];

    protected $casts = [
        'parsed_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
