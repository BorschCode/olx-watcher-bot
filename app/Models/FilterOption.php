<?php

namespace App\Models;

use Database\Factories\FilterOptionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read Collection<int, Watcher> $watchers
 * @property-read int|null $watchers_count
 *
 * @method static \Database\Factories\FilterOptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FilterOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FilterOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FilterOption query()
 *
 * @mixin \Eloquent
 */
class FilterOption extends Model
{
    /** @use HasFactory<FilterOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'group',
        'label',
        'key',
        'value',
        'has_range',
    ];

    protected function casts(): array
    {
        return [
            'has_range' => 'boolean',
        ];
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(Watcher::class)
            ->withPivot('value_from', 'value_to');
    }
}
