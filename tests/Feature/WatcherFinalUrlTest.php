<?php

use App\Enums\HttpMethod;
use App\Enums\WatcherSource;
use App\Models\Watcher;

test('finalUrl returns the search-link URL as-is for GetHtml watchers', function () {
    $url = 'https://www.olx.ua/uk/nedvizhimost/doma/prodazha-domov/kiev/?currency=USD&search%5Bdist%5D=30&search%5Border%5D=filter_float_price%3Adesc';

    $watcher = new Watcher([
        'source' => WatcherSource::Olx->value,
        'method' => HttpMethod::GetHtml->value,
        'url' => $url,
    ]);

    expect($watcher->final_url)->toBe($url);
});

test('finalUrl returns null for watchers without a URL', function () {
    $watcher = new Watcher([
        'source' => WatcherSource::Olx->value,
        'method' => HttpMethod::Get->value,
        'url' => null,
    ]);

    expect($watcher->final_url)->toBeNull();
});
