<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends BaseModel
{
    use HasFactory;
    public $incrementing = false;  // ✅ UUID primary key
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'status', 'started_at',
        'submitted_at', 'duration_minutes',
        'total_questions', 'total_score', 'ends_at'
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'ends_at'      => 'datetime', // ✅ IMPORTANT
        'submitted_at' => 'datetime',
        'fee_paid'     => 'boolean',
        'fee_refunded' => 'boolean',
    ];

    protected $appends = ['ends_at'];

    public function getEndsAtAttribute()
    {
        if (!$this->started_at || !$this->duration_minutes) {
            return null;
        }

        return $this->started_at->copy()
            ->addMinutes($this->duration_minutes);
    }

    /* ===================== RELATIONSHIPS ===================== */
    protected static function booted()
    {
        static::creating(function ($model) {
            $model->id ??= (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function answers()
    {
        return $this->hasMany(ExamAnswer::class);
    }

}
