<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'date', 'accessed_at' => 'date'];
    }
}
