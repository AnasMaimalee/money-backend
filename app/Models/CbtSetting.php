<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class CbtSetting extends Model
{
    protected $fillable = [
        'subjects_count',
        'questions_per_subject',
        'duration_minutes',
        'exam_fee',
    ];

    public static function get(): self
{
    return static::query()->firstOrCreate(
        ['id' => 1], // look for id 1
        [
            'subjects_count' => 4,
            'questions_per_subject' => 50,
            'duration_minutes' => 40,
            'exam_fee' => 100,
        ]
    );
}
}

