<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'subject_id',
        'score',
    ];

    /* ===================== RELATIONSHIPS ===================== */

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
