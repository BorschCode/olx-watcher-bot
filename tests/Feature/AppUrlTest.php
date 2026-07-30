<?php

use App\Support\AppUrl;
use Illuminate\Support\Facades\URL;

test('base returns configured app url without trailing slash', function () {
    config(['app.url' => 'http://192.168.2.140:8022/']);

    expect(AppUrl::base())->toBe('http://192.168.2.140:8022');
});

test('adminWatchers returns filament watchers index url', function () {
    config(['app.url' => 'http://192.168.2.140:8022']);
    URL::forceRootUrl('http://192.168.2.140:8022');

    expect(AppUrl::adminWatchers())->toBe('http://192.168.2.140:8022/admin/watchers');
});
