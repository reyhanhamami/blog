<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningPath extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(LearningPathItem::class)->orderBy('sort_order');
    }
}
