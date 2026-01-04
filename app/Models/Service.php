<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Service extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'customer_price',
        'admin_payout',
        'active',
    ];


    protected static function booted()
    {
        static::creating(function ($service) {
            $service->id = (string) \Str::uuid();
        });
    }

    public function requests()
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function platformProfit(): float
    {
        return $this->cusotmer_price - $this->admin_payout;
    }

}
