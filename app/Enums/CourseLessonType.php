<?php

namespace App\Enums;

enum CourseLessonType: string
{
    case Custom = 'custom';
    case Article = 'article';
    case Video = 'video';
    case Quiz = 'quiz';
}
