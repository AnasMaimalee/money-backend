<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Services\CBT\ExamTimerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use App\Models\Exam;
class ExamTimerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ExamTimerService $service
    ) {}

    /**
     * Auto-submit exam if time has elapsed
     */
    public function checkAndSubmit(string $exam): JsonResponse
    {
        $result = $this->service->autoSubmitIfTimeUp($exam);

        if (!$result) {
            return response()->json([
                'status' => 'running',
                'message' => 'Exam time not yet elapsed'
            ]);
        }

        return response()->json([
            'status' => 'submitted',
            'message' => 'Exam auto-submitted successfully',
            'data' => $result
        ]);
    }

    public function heartbeat(Exam $exam, ExamTimerService $service)
    {
        $this->authorize('view', $exam);

        return response()->json(
            $service->heartbeat($exam)
        );
    }

}
