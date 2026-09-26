<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function modules()
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }
}
