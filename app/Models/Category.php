<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'url'
    ];

    public function listings()
    {
        return $this->hasMany(Listing::class);
    }
}
