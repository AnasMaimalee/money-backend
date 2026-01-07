<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserManagementRepository
{
    /**
     * Get paginated users (regular users only, exclude admins/superadmin)
     */
    public function getPaginatedUsers(int $perPage = 20): LengthAwarePaginator
    {
        return User::with('wallet')
            ->whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', ['administrator', 'superadmin']);
            })
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }
    /**
     * Find user by ID
     */
    public function findById(string $id)
    {
        return User::with('wallet')->findOrFail($id);
    }

    /**
     * Find trashed user by ID
     */
    public function findTrashedById(string $id)
    {
        return User::onlyTrashed()->with('wallet')->findOrFail($id);
    }

    /**
     * Search users by name, email, or phone
     */
    public function search(string $query, int $perPage = 20): LengthAwarePaginator
    {
        return User::with('wallet')
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['administrator', 'superadmin']);
            })
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->latest()
            ->paginate($perPage);
    }
}
