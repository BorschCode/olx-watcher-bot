<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'url',
        'price',
        'parsed_at'
    ];

    protected $casts = [
        'parsed_at' => 'datetime'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
