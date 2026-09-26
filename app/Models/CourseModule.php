<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseModule extends Model
{
    protected $guarded = ['id'];

    public function lessons()
    {
        return $this->hasMany(CourseLesson::class)->orderBy('sort_order');
    }
}
