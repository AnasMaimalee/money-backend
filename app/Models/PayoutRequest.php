<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Enums\PayoutStatus;
class PayoutRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'admin_id',
        'amount',
        'status',
        'reference',
        'balance_snapshot',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'status' => PayoutStatus::class,
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $keyType = 'string';      // UUID is a string
    public $incrementing = false;

    /* =======================
     |  RELATIONSHIPS
     |=======================*/

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* =======================
     |  SCOPES
     |=======================*/

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function administrator()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    // Optionally, if you have a user who made the request
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    /**
     * Superadmin who approved payout
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
