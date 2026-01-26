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
            [
                'name' => 'Jamb Original Result',
                'description' => 'Printing Jamb Original Result',
                'customer_price' => 5000,
                'admin_payout' => 3000,
            ],
            [
                'name' => 'Jamb Admission Letter',
                'description' => 'Printing Jamb Admission Letter',
                'customer_price' => 4000,
                'admin_payout' => 2500,
            ],
            [
                'name' => 'Checking Admission Status',
                'description' => 'Printing Jamb Admission Status',
                'customer_price' => 2000,
                'admin_payout' => 1000,
            ],
            [
                'name' => 'JAMB Results Notifications',
                'description' => 'Printing Jamb Result Notification',
                'customer_price' => 1500,
                'admin_payout' => 800,
            ],
            [
                'name' => 'JAMB Upload Status',
                'description' => 'Printing Jamb Upload Status',
                'customer_price' => 2500,
                'admin_payout' => 1200,
            ],
            [
                'name' => 'Jamb PIN Binding request',
                'description' => 'Printing jamb PIN Binding',
                'customer_price' => 2500,
                'admin_payout' => 1200,
            ],
        ];

        foreach ($services as $s) {
            Service::updateOrCreate(
                ['slug' => Str::slug($s['name'])], // 👈 unique identifier
                [
                    'id' => Str::uuid(),
                    'name' => $s['name'],
                    'slug' => Str::slug($s['name']), // 🔥 SLUG ADDED
                    'description' => $s['description'],
                    'customer_price' => $s['customer_price'],
                    'admin_payout' => $s['admin_payout'],
                    'active' => true,
                ]
            );
        }
    }
}
