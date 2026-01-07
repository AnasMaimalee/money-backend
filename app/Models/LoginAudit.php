<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoginAudit extends Model
{
    public $incrementing = false; // UUID not auto-increment
    protected $keyType = 'string'; // UUID is string

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'location',
        'success',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
