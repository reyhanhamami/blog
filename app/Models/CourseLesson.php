<?php

namespace App\Models;

use App\Enums\CourseLessonType;
use Illuminate\Database\Eloquent\Model;

class CourseLesson extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => CourseLessonType::class, 'requires_pass' => 'boolean'];
    }

    public function module()
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
