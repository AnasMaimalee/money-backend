<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\CBT\ResultService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ResultController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        protected ResultService $resultService
    ) {}

    public function show(Exam $exam)
    {
        try {
            return response()->json(
                $this->resultService->getResult($exam)
            );

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function summary(Exam $exam, ResultService $service)
    {
        $this->authorize('view', $exam);

        return response()->json(
            $service->summary($exam)
        );
    }

}
