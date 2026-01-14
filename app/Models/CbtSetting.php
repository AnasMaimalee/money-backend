<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CbtSetting extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'subjects_count',
        'questions_per_subject',
        'duration_minutes',
        'exam_fee',
    ];
    /* ===================== RELATIONSHIPS ===================== */


}
