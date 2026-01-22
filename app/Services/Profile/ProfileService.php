<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Models\BankAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    // Get profile + bank account
    public function profile(User $user)
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'bank_account' => $user->bankAccount // relation
        ];
    }

    // Create or update bank account
   public function updateBank(User $user, array $data)
    {
        $account = $user->bankAccount;

        if ($account) {
            $account->update($data);
        } else {
            $account = BankAccount::create(array_merge($data, ['user_id' => $user->id]));
        }

        return [
            'message' => 'Bank account saved successfully',
            'data' => $account
        ];
    }


    // Update password
    public function updatePassword(User $user, string $current, string $new)
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect',
            ]);
        }

        $user->update(['password' => Hash::make($new)]);

        return [
            'message' => 'Password updated successfully',
        ];
    }
}
