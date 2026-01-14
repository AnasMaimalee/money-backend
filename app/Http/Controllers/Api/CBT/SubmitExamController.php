<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\CBT\SubmitExamService;

class SubmitExamController extends Controller
{
    public function __construct(
        protected SubmitExamService $submitExamService
    ) {}

    public function submit(Exam $exam)
    {
        try {
            $result = $this->submitExamService->submit($exam);

            return response()->json([
                'message' => 'Exam submitted successfully',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
