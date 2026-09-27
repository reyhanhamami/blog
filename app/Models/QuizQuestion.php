<?php

namespace App\Models;

use App\Enums\QuizQuestionType;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => QuizQuestionType::class, 'points' => 'integer'];
    }

    public function options()
    {
        return $this->hasMany(QuizOption::class);
    }
}
