<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $olx_id
 * @property string $name
 * @property string $slug
 * @property bool $sync
 * @property array<array-key, mixed>|null $options
 * @property bool $show_location
 * @property bool $show_rooms
 * @property bool $show_square_meters
 * @property bool $show_sale_date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Listing> $listings
 * @property-read int|null $listings_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSync($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Category extends Model
{
    protected $fillable = [
        'olx_id',
        'name',
        'slug',
        'sync',
        'options',
        'show_location',
        'show_rooms',
        'show_square_meters',
        'show_sale_date',
    ];

    protected function casts(): array
    {
        return [
            'sync' => 'boolean',
            'options' => 'array',
            'show_location' => 'boolean',
            'show_rooms' => 'boolean',
            'show_square_meters' => 'boolean',
            'show_sale_date' => 'boolean',
        ];
    }

    public function listings()
    {
        return $this->hasMany(Listing::class);
    }
}
