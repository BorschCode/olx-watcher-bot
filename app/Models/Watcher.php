<?php

namespace App\Models;

use App\Enums\HttpMethod;
use Database\Factories\WatcherFactory;
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
    ];

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'request_body' => 'array',
            'last_seen_id' => 'integer',
        ];
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

    public function finalUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->method !== HttpMethod::Get || $this->url === null) {
                    return null;
                }

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
                        if ($existing === null) {
                            $params[$option->key] = $option->value;
                        } else {
                            $params[$option->key] = array_merge((array) $existing, [$option->value]);
                        }
                    } elseif ($option->pivot->value_from !== null) {
                        $params[$option->key] = $option->pivot->value_from;
                    }
                }

                return empty($params)
                    ? $this->url
                    : $this->url.'?'.http_build_query($params);
            },
        );
    }
}
