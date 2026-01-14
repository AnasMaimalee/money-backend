<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1️⃣ Seed roles and permissions
        $this->call(RolePermissionSeeder::class);
        $this->call(ServiceSeeder::class);
        $this->call(CbtSettingSeeder::class);
        $this->call(SubjectSeeder::class);
        // 2️⃣ Create users and assign roles
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'eduoasis2025@gmail.com',
            'password' => bcrypt('password'),
        ]);
        $superAdmin->assignRole('superadmin');

        $adminOne = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'anasmaimalee@gmail.com',
            'password' => bcrypt('password'),
        ]);
        $adminOne->assignRole('administrator');

        $normalUser = User::factory()->create([
            'name' => 'Anas Maimalee',
            'email' => 'anasment6@gmail.com',
            'password' => bcrypt('password'),
        ]);
        $normalUser->assignRole('user');

        $this->call(WalletSeeder::class);
    }
}
