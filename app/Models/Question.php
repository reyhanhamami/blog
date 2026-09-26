<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = ['id'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
