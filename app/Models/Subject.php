<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    /* ===================== RELATIONSHIPS ===================== */

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
