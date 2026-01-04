<?php

namespace App\Models;

use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;

class JambAdmissionLetterRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'service_id',

        'email',
        'phone_number',
        'profile_code',
        'registration_number',

        'customer_price',
        'admin_payout',
        'platform_profit',

        'status',
        'is_paid',

        'taken_by',
        'completed_by',
        'approved_by',
        'rejected_by',

        'result_file',
        'rejection_reason',
        'admin_note',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'customer_price' => 'decimal:2',
        'admin_payout' => 'decimal:2',
        'platform_profit' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($request) {
            $request->id = (string) \Str::uuid();
        });
    }

    /* ================= RELATIONSHIPS ================= */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function takenBy()
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }


}
