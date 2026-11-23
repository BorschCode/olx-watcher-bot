<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PriceHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $saved_ad_id
 * @property int|null $old_price
 * @property int|null $new_price
 * @property CarbonImmutable $changed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read SavedAd $savedAd
 *
 * @method static \Database\Factories\PriceHistoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceHistory query()
 *
 * @mixin \Eloquent
 */
class PriceHistory extends Model
{
    /** @use HasFactory<PriceHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'saved_ad_id',
        'old_price',
        'new_price',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'integer',
            'new_price' => 'integer',
            'changed_at' => 'immutable_datetime',
        ];
    }

    public function savedAd(): BelongsTo
    {
        return $this->belongsTo(SavedAd::class, 'saved_ad_id');
    }
}
