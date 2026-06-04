<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SavedAdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $olx_id
 * @property string $source
 * @property string $telegram_chat_id
 * @property string $title
 * @property string $url
 * @property int|null $price
 * @property string[]|null $images
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $refreshed_at
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 *
 * @method static SavedAdFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedAd newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedAd newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedAd query()
 *
 * @mixin \Eloquent
 */
class SavedAd extends Model
{
    /** @use HasFactory<SavedAdFactory> */
    use HasFactory;

    protected $fillable = [
        'olx_id',
        'source',
        'telegram_chat_id',
        'title',
        'url',
        'price',
        'images',
        'published_at',
        'refreshed_at',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'olx_id' => 'integer',
            'price' => 'integer',
            'images' => 'array',
            'published_at' => 'immutable_datetime',
            'refreshed_at' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
        ];
    }
}
