<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $guarded = ['id'];

    public function options()
    {
        return $this->hasMany(QuizOption::class);
    }
}
