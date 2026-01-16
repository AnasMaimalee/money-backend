<?php

namespace App\Http\Controllers\Api\CBT\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Cbt\CbtSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CbtSettingController extends Controller
{
    public function __construct(
        private CbtSettingService $service
    ) {}

    /**
     * Get CBT Settings
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getSettings(),
        ]);
    }

    /**
     * Update CBT Settings
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $updated = $this->service->updateSettings($request->all());

            return response()->json([
                'success' => true,
                'message' => 'CBT settings updated successfully!',
                'data' => $updated,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
            ], 500);
        }
    }
}
