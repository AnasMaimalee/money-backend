<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BankAccount extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted()
    {
        static::creating(function ($bank) {
            $bank->id = (string) Str::uuid();
        });

    }
    protected $fillable = [
        'user_id',
        'bank_name',
        'account_name',
        'account_number',
        'bank_code',
        'recipient_code',
        'recipient_verified_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
