<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'category_id'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
