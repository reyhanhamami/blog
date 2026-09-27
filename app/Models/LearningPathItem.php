<?php

namespace App\Models;

use App\Enums\LearningPathItemType;
use Illuminate\Database\Eloquent\Model;

class LearningPathItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => LearningPathItemType::class];
    }

    public function path()
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
