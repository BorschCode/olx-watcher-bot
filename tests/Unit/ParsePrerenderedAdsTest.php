<?php

use App\Console\Commands\SyncOlxListings;
use App\Models\Listing;

/**
 * @return array<int, array<string, mixed>>|null
 */
function parsePrerenderedAdsFromHtml(string $html): ?array
{
    $command = new SyncOlxListings;
    $method = new ReflectionMethod(SyncOlxListings::class, 'parsePrerenderedAds');

    return $method->invoke($command, $html);
}

test('parses prerendered state with spaced assignment operator', function () {
    $html = <<<'HTML'
<html><body><script>
window.__PRERENDERED_STATE__ = "{\"listing\":{\"listing\":{\"ads\":[{\"id\":123,\"title\":\"Test flat\",\"url\":\"https://example.com\",\"price\":{\"regularPrice\":{\"value\":1000}},\"params\":[{\"key\":\"number_of_rooms\",\"value\":\"3\"},{\"key\":\"total_area\",\"value\":\"75 м²\"}],\"photos\":[]}]}}}"
</script></body></html>
HTML;

    $ads = parsePrerenderedAdsFromHtml($html);

    expect($ads)->toHaveCount(1)
        ->and(Listing::extractRooms($ads[0]))->toBe('3')
        ->and(Listing::extractTotalArea($ads[0]))->toBe('75 м²')
        ->and(Listing::extractPrice($ads[0]))->toBe(1000);
});

test('parses prerendered state without spaces around assignment operator', function () {
    $html = <<<'HTML'
<html><body><script>
window.__PRERENDERED_STATE__="{\"listing\":{\"listing\":{\"ads\":[{\"id\":456,\"title\":\"Test house\",\"url\":\"https://example.com\",\"price\":{\"regularPrice\":{\"value\":2000}},\"params\":[{\"key\":\"number_of_rooms\",\"value\":\"2\"},{\"key\":\"total_area\",\"value\":\"45 м²\"}],\"photos\":[]}]}}}"
</script></body></html>
HTML;

    $ads = parsePrerenderedAdsFromHtml($html);

    expect($ads)->toHaveCount(1)
        ->and(Listing::extractRooms($ads[0]))->toBe('2')
        ->and(Listing::extractTotalArea($ads[0]))->toBe('45 м²');
});
