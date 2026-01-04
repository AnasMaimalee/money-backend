<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Jamb Original Result', 'customer_price' => 5000, 'admin_payout' => 3000],
            ['name' => 'Jamb Admission Letter', 'customer_price' => 4000, 'admin_payout' => 2500],
            ['name' => 'Checking Admission Status', 'customer_price' => 2000, 'admin_payout' => 1000],
            ['name' => 'JAMB Results Notifications', 'customer_price' => 1500, 'admin_payout' => 800],
            ['name' => 'JAMB Upload Status', 'customer_price' => 2500, 'admin_payout' => 1200],
        ];

        foreach ($services as $s) {
            Service::create([
                'id' => Str::uuid(),
                'name' => $s['name'],
                'description' => $s['name'] . ' service',
                'customer_price' => $s['customer_price'],
                'admin_payout' => $s['admin_payout'],
                'active' => true,
            ]);
        }
    }
}
