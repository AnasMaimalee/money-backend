<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamSession extends Model
{
    use HasUuids;

    protected $table = 'exam_sessions';

    protected $fillable = [
        'exam_id',
        'user_id',
        'starts_at',
        'ends_at',
        'submitted_at',
        'is_submitted',
    ];

    protected $casts = [
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'submitted_at' => 'datetime',
        'is_submitted' => 'boolean',
    ];

    /* ================== RELATIONSHIPS ================== */

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
