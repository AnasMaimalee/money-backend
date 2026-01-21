<?php

namespace App\Http\Controllers\Api\CBT\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CBT\SuperAdmin\UpdateCbtSettingsRequest;
use App\Services\CBT\SuperAdmin\CbtSettingService;
use Illuminate\Http\JsonResponse;

class CbtSettingController extends Controller
{
    public function __construct(
        private CbtSettingService $service
    ) {}

    /**
     * GET /cbt/superadmin/settings
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->fetch(),
        ]);
    }

    /**
     * PUT /cbt/superadmin/settings
     */
    public function update(UpdateCbtSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully!',
            'data' => $this->service->update($request->validated()),
        ]);
    }
}
