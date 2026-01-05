<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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
        'amount'           => 'decimal:2',
        'balance_snapshot' => 'decimal:2',
        'approved_at'      => 'datetime',
    ];

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
}
