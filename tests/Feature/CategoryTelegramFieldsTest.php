<?php

use App\Models\Category;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Оренда квартир',
        'slug' => 'orenda-kvartir',
        'olx_id' => 1760,
    ]);
});

test('telegram field toggles default to true on new category', function () {
    expect($this->category->show_location)->toBeTrue()
        ->and($this->category->show_rooms)->toBeTrue()
        ->and($this->category->show_square_meters)->toBeTrue()
        ->and($this->category->show_sale_date)->toBeTrue();
});

test('telegram field toggles can be disabled per category', function () {
    $this->category->update([
        'show_rooms' => false,
        'show_square_meters' => false,
    ]);

    expect($this->category->fresh()->show_location)->toBeTrue()
        ->and($this->category->fresh()->show_rooms)->toBeFalse()
        ->and($this->category->fresh()->show_square_meters)->toBeFalse()
        ->and($this->category->fresh()->show_sale_date)->toBeTrue();
});
