<?php

use App\Models\Listing;

test('extracts rooms and total area from prerendered state params', function () {
    $offer = [
        'params' => [
            ['key' => 'price', 'value' => ['value' => 10000]],
            [
                'key' => 'number_of_rooms',
                'name' => 'Кількість кімнат',
                'type' => 'input',
                'value' => '3',
                'normalizedValue' => '3',
            ],
            [
                'key' => 'total_area',
                'name' => 'Загальна площа',
                'type' => 'input',
                'value' => '75 м²',
                'normalizedValue' => '75',
            ],
        ],
    ];

    expect(Listing::extractRooms($offer))->toBe('3')
        ->and(Listing::extractTotalArea($offer))->toBe('75 м²')
        ->and(Listing::extractPrice($offer))->toBe(10000);
});

test('extracts rooms and total area from graphql params', function () {
    $offer = [
        'params' => [
            [
                'key' => 'number_of_rooms',
                'name' => 'Кількість кімнат',
                'type' => 'input',
                'value' => [
                    '__typename' => 'GenericParam',
                    'key' => '2',
                    'label' => '2',
                ],
            ],
            [
                'key' => 'total_area',
                'name' => 'Загальна площа',
                'type' => 'input',
                'value' => [
                    '__typename' => 'GenericParam',
                    'key' => '65',
                    'label' => '65 м²',
                ],
            ],
        ],
    ];

    expect(Listing::extractRooms($offer))->toBe('2')
        ->and(Listing::extractTotalArea($offer))->toBe('65 м²');
});

test('extracts rooms from number_of_rooms_string fallback', function () {
    $offer = [
        'params' => [
            [
                'key' => 'number_of_rooms_string',
                'name' => 'Кількість кімнат',
                'type' => 'select',
                'value' => [
                    '__typename' => 'GenericParam',
                    'key' => 'dvuhkomnatnye',
                    'label' => '2 кімнати',
                ],
            ],
        ],
    ];

    expect(Listing::extractRooms($offer))->toBe('2 кімнати');
});

test('returns null when estate params are missing', function () {
    $offer = [
        'params' => [
            ['key' => 'price', 'value' => ['value' => 5000]],
        ],
    ];

    expect(Listing::extractRooms($offer))->toBeNull()
        ->and(Listing::extractTotalArea($offer))->toBeNull();
});

test('extracts city from graphql location', function () {
    $offer = [
        'location' => [
            'city' => ['id' => 123, 'name' => 'Гнідин', 'normalized_name' => 'gnedin'],
            'region' => ['id' => 32, 'name' => 'Київська область', 'normalized_name' => 'kyivska_oblast'],
        ],
    ];

    expect(Listing::extractCity($offer))->toBe('Гнідин');
});

test('extracts city from flat city name', function () {
    $offer = [
        'city' => ['name' => 'Львів'],
    ];

    expect(Listing::extractCity($offer))->toBe('Львів');
});

test('extracts city from locationCityName (autoria fallback)', function () {
    $offer = [
        'locationCityName' => 'Одеса',
    ];

    expect(Listing::extractCity($offer))->toBe('Одеса');
});

test('extracts city from ld+json areaServed name', function () {
    $offer = [
        'areaServed' => ['name' => 'Жмеринка'],
    ];

    expect(Listing::extractCity($offer))->toBe('Жмеринка');
});

test('returns null when no city data present', function () {
    $offer = [
        'params' => [
            ['key' => 'price', 'value' => ['value' => 5000]],
        ],
    ];

    expect(Listing::extractCity($offer))->toBeNull();
});

test('extracts full address line from ld+json postal address without country', function () {
    $offer = [
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'вул. Болсуновська, 13-15',
            'addressLocality' => 'Київ',
            'addressRegion' => 'Київська область',
            'postalCode' => '01014',
            'addressCountry' => 'UA',
        ],
    ];

    expect(Listing::extractLocation($offer))->toBe('вул. Болсуновська, 13-15, Київ, Київська область, 01014');
});

test('extracts address line skipping missing parts from ld+json postal address', function () {
    $offer = [
        'address' => [
            'streetAddress' => 'вул. Болсуновська, 13-15',
            'addressLocality' => 'Київ',
            'addressRegion' => 'Київська область',
        ],
    ];

    expect(Listing::extractLocation($offer))->toBe('вул. Болсуновська, 13-15, Київ, Київська область');
});

test('extracts location from areaServed when no address present', function () {
    $offer = [
        'areaServed' => ['name' => 'Жмеринка'],
    ];

    expect(Listing::extractLocation($offer))->toBe('Жмеринка');
});

test('extracts graphql city and region without search-area city alone', function () {
    $offer = [
        'location' => [
            'city' => ['id' => 123, 'name' => 'Гостомель', 'normalized_name' => 'gomel'],
            'region' => ['id' => 32, 'name' => 'Київська область', 'normalized_name' => 'kyivska_oblast'],
        ],
    ];

    expect(Listing::extractLocation($offer))->toBe('Гостомель, Київська область');
});

test('extracts graphql city alone when region missing', function () {
    $offer = [
        'location' => [
            'city' => ['id' => 123, 'name' => 'Гнідин', 'normalized_name' => 'gnedin'],
        ],
    ];

    expect(Listing::extractLocation($offer))->toBe('Гнідин');
});

test('extracts flat city name fallback', function () {
    $offer = [
        'city' => ['name' => 'Львів'],
    ];

    expect(Listing::extractLocation($offer))->toBe('Львів');
});

test('returns null when no location data present', function () {
    $offer = [
        'params' => [
            ['key' => 'price', 'value' => ['value' => 5000]],
        ],
    ];

    expect(Listing::extractLocation($offer))->toBeNull();
});
