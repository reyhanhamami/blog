<?php

namespace App\Enums;

enum LearningPathItemType: string
{
    case Article = 'article';
    case Video = 'video';
    case Quiz = 'quiz';
}
