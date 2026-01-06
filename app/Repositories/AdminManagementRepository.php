<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AdminManagementRepository
{
    /**
     * Get all administrators (with optional trashed)
     */
    public function getAllAdministrators(bool $withTrashed = false): Collection
    {
        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'administrator');
        })->with('wallet');

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->latest()->get();
    }

    /**
     * Paginated administrators
     */
    public function getPaginatedAdministrators(bool $withTrashed = false, int $perPage = 20)
    {
        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'administrator');
        })->with('wallet');

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find admin by ID (with optional trashed)
     */
    public function findById(string $id, bool $withTrashed = false)
    {
        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'administrator');
        });

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }

    /**
     * Find trashed admin by ID
     */
    public function findTrashedById(string $id)
    {
        return User::onlyTrashed()
            ->whereHas('roles', function ($q) {
                $q->where('name', 'administrator');
            })
            ->findOrFail($id);
    }
}
