<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminManagementService;
use Illuminate\Http\JsonResponse;

class AdminManagementController extends Controller
{
    public function __construct(protected AdminManagementService $service)
    {
    }

    /**
     * List all administrators
     */
    public function index(): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $admins = $this->service->getAdministrators(paginated: true, perPage: 20);

        return response()->json([
            'message' => 'Administrators retrieved successfully',
            'data' => $admins,
        ]);
    }

    /**
     * Soft delete administrator
     */
    public function destroy($adminId): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $admin = $this->service->repository->findById($adminId);

        $result = $this->service->deleteAdministrator($admin);

        return response()->json($result);
    }

    /**
     * Restore deleted administrator
     */
    public function restore($id): JsonResponse
    {
        $this->service->ensureSuperadmin();

        $result = $this->service->restoreAdministrator($id);

        return response()->json($result);
    }
}
