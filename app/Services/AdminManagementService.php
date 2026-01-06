<?php

namespace App\Services;

use App\Repositories\AdminManagementRepository;

class AdminManagementService
{
    public function __construct(protected AdminManagementRepository $repository)
    {
    }

    public function findAdministratorById(string $id)
    {
        return $this->repository->findById($id);
    }

    public function getAdministrators(bool $paginated = true, int $perPage = 20)
    {
        if ($paginated) {
            return $this->repository->getPaginatedAdministrators(false, $perPage);
        }

        return $this->repository->getAllAdministrators();
    }

    public function deleteAdministrator($admin): array
    {
        if (auth()->id() === $admin->id) {
            abort(422, 'You cannot delete yourself.');
        }

        $admin->delete();

        return [
            'message' => 'Administrator deleted successfully',
            'data' => $admin->fresh()->load('wallet')
        ];
    }

    public function restoreAdministrator(string $id): array
    {
        $admin = $this->repository->findTrashedById($id);
        $admin->restore();

        return [
            'message' => 'Administrator restored successfully',
            'data' => $admin->fresh()->load('wallet')
        ];
    }

    public function ensureSuperadmin(): void
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403, 'Only superadmin can perform this action.');
        }
    }
}
