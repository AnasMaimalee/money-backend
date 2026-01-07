<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\Mail;
use App\Mail\WalletCredited;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $walletService = app(WalletService::class);

        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->info('No users found. Please create users first.');
            return;
        }

        foreach ($users as $user) {
            // Determine funding based on role
            if ($user->hasRole('superadmin') || $user->hasRole('administrator')) {
                $amount = 50000;
            } else {
                $amount = 100000;
            }

            // ✅ Use public seeder-safe method instead of protected credit()
            $tx = $walletService->seedCredit(
                $user,
                $amount,
                'Initial funding via WalletSeeder'
            );

            // Send email after credit
            Mail::to($user->email)->send(
                new WalletCredited(
                    $user,
                    $amount,
                    $tx->balance_after, // updated balance
                    'Initial funding via WalletSeeder' // reason
                )
            );

            $this->command->info("Credited ₦{$amount} to {$user->name}'s wallet ({$user->email})");
        }

        $this->command->info('All users have been funded and notified successfully!');
    }
}
