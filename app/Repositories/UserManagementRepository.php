<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * Search users by name, email, or phone (ACTIVE users only)
     */
    public function search(?string $search = null, ?string $role = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = User::with('wallet')
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['administrator', 'superadmin']);
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get TRASHED users only
     */
    public function getTrashedUsers(?string $search = null, ?string $role = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = User::onlyTrashed()->with('wallet');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Find user by ID (includes trashed)
     */
    public function findById(string $id)
    {
        return User::withTrashed()->with('wallet')->findOrFail($id);
    }

    /**
     * Find TRASHED user by ID
     */
    public function findTrashedById(string $id)
    {
        return User::onlyTrashed()->with('wallet')->findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }
}
