<?php

namespace Database\Factories;

use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayoutRequestFactory extends Factory
{
    protected $model = PayoutRequest::class;

    public function definition(): array
    {
        return [
            'admin_id'         => User::factory(),
            'amount'           => $this->faker->numberBetween(1000, 50000),
            'balance_snapshot' => $this->faker->numberBetween(50000, 100000),
            'status'           => 'pending',
        ];
    }
}
