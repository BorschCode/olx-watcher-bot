<?php

namespace App\Models;

use App\Enums\HttpMethod;
use Database\Factories\WatcherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $telegram_chat_id
 * @property HttpMethod $method
 * @property int $category_id
 * @property int|null $city_id
 * @property string|null $url
 * @property array<string, mixed>|null $request_body
 * @property int|null $last_seen_id
 * @property bool $is_active
 * @property-read Category|null $category
 * @property-read City|null $city
 * @property-read Collection<int, FilterOption> $filterOptions
 * @property-read int|null $filter_options_count
 * @property-read string|null $final_url
 *
 * @method static WatcherFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Watcher newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Watcher newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Watcher query()
 * @method static Builder<static> active()
 *
 * @mixin \Eloquent
 */
class Watcher extends Model
{
    /** @use HasFactory<WatcherFactory> */
    use HasFactory;

    protected $fillable = [
        'telegram_chat_id',
        'method',
        'category_id',
        'city_id',
        'url',
        'request_body',
        'last_seen_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'request_body' => 'array',
            'last_seen_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function filterOptions(): BelongsToMany
    {
        return $this->belongsToMany(FilterOption::class)
            ->withPivot('value_from', 'value_to');
    }

    /** @return array<string, mixed> */
    public function filterParams(): array
    {
        $params = [];

        foreach ($this->filterOptions as $option) {
            if ($option->has_range) {
                if ($option->pivot->value_from !== null) {
                    $params[$option->key.':from'] = $option->pivot->value_from;
                }
                if ($option->pivot->value_to !== null) {
                    $params[$option->key.':to'] = $option->pivot->value_to;
                }
            } elseif ($option->value !== null) {
                $existing = $params[$option->key] ?? null;
                $params[$option->key] = $existing === null
                    ? $option->value
                    : array_merge((array) $existing, [$option->value]);
            } elseif ($option->pivot->value_from !== null) {
                $params[$option->key] = $option->pivot->value_from;
            }
        }

        return $params;
    }

    /**
     * Build GraphQL searchParameters from this watcher's fields.
     *
     * @return array<int, array{key: string, value: string}>
     */
    public function buildSearchParameters(int $offset = 0, int $limit = 40): array
    {
        $olxCategoryId = $this->category?->olx_id ?? $this->category_id;

        $params = [
            ['key' => 'offset', 'value' => (string) $offset],
            ['key' => 'limit', 'value' => (string) $limit],
            ['key' => 'category_id', 'value' => (string) $olxCategoryId],
            ['key' => 'currency', 'value' => 'UAH'],
            ['key' => 'sort_by', 'value' => 'created_at:desc'],
        ];

        if ($this->city_id !== null && $this->city !== null) {
            if ($this->city->olx_id !== null) {
                $params[] = ['key' => 'city_id', 'value' => (string) $this->city->olx_id];
            }

            if ($this->city->region_id !== null) {
                $params[] = ['key' => 'region_id', 'value' => (string) $this->city->region_id];
            }
        }

        foreach ($this->filterParams() as $key => $value) {
            if (is_array($value)) {
                foreach (array_values($value) as $index => $v) {
                    $params[] = ['key' => "{$key}[{$index}]", 'value' => (string) $v];
                }
            } else {
                $params[] = ['key' => $key, 'value' => (string) $value];
            }
        }

        return $params;
    }

    public function finalUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->method !== HttpMethod::Get || $this->url === null) {
                    return null;
                }

                if (str_contains($this->url, '?')) {
                    return $this->url;
                }

                $params = $this->filterParams();

                return empty($params)
                    ? $this->url
                    : $this->url.'?'.http_build_query($params);
            },
        );
    }
}
