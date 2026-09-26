<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningPathItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
