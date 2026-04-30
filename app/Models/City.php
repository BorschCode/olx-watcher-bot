<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'slug',
        'name'
    ];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
