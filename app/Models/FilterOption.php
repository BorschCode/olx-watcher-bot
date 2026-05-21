<?php

namespace App\Models;

use Database\Factories\FilterOptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $group
 * @property string $label
 * @property string $key
 * @property string|null $value
 * @property bool $has_range
 * @property array<string>|null $applicable_methods null = applies to all filter-capable methods
 * @property-read Collection<int, Watcher> $watchers
 * @property-read int|null $watchers_count
 *
 * @method static \Database\Factories\FilterOptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FilterOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FilterOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FilterOption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FilterOption forMethod(string $method)
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
        'applicable_methods',
    ];

    protected function casts(): array
    {
        return [
            'has_range' => 'boolean',
            'applicable_methods' => 'array',
        ];
    }

    /** Scope to options that apply to the given watcher method. */
    public function scopeForMethod(Builder $query, string $method): void
    {
        $query->where(function (Builder $q) use ($method) {
            $q->whereNull('applicable_methods')
                ->orWhereJsonContains('applicable_methods', $method);
        });
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(Watcher::class)
            ->withPivot('value_from', 'value_to');
    }
}
