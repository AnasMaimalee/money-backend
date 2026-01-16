<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CbtSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'subjects_count',
        'questions_per_subject',
        'duration_minutes',
        'exam_fee',
    ];

    protected $casts = [
        'subjects_count' => 'integer',
        'questions_per_subject' => 'integer',
        'duration_minutes' => 'integer',
        'exam_fee' => 'decimal:2',
    ];

    public static function getSettings(): self
    {
        return self::firstOrCreate([]);
    }
}
