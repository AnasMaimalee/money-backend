<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamAnswer extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'question_id',
        'selected_option',
        'is_correct',
    ];

    /* ===================== RELATIONSHIPS ===================== */

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
