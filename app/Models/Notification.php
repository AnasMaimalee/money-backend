<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Notification extends DatabaseNotification
{
    use HasFactory;


    // -----------------------
    // UUID primary key
    // -----------------------
    public $incrementing = false;
    protected $keyType = 'string';

    // -----------------------
    // Fillable fields
    // -----------------------
    protected $fillable = [
        'id',
        'type',               // Notification class
        'notifiable_type',    // e.g., App\Models\User
        'notifiable_id',      // UUID of user
        'data',               // JSON payload
        'read_at',            // timestamp for read
    ];

    // -----------------------
    // Casts
    // -----------------------
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    // -----------------------
    // Boot to auto-generate UUID
    // -----------------------
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // -----------------------
    // Relationships
    // -----------------------
    public function notifiable()
    {
        return $this->morphTo();
    }

    // -----------------------
    // Scopes
    // -----------------------
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    // -----------------------
    // Mark notification as read
    // -----------------------
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }
}
