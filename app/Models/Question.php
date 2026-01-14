<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Question extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id',
        'subject_id',
        'question',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_option',
        'image',
        'year'
    ];

    protected $keyType = 'string';  // UUIDs are strings
    public $incrementing = false;   // Disable auto-increment

    /* ===================== RELATIONSHIPS ===================== */

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function examAnswers()
    {
        return $this->hasMany(ExamAnswer::class);
    }


    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = Str::uuid();
            }
        });
    }

}
