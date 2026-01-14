<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\CBT\ExamService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
class ExamController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ExamService $examService
    ) {}

    public function start(Request $request)
    {
        $request->validate([
            'subjects' => 'required|array|min:1|max:4',
            'subjects.*' => 'exists:subjects,id',
        ]);

        try {
            $exam = $this->examService
                ->startExam($request->user()->id, $request->subjects);

            return response()->json([
                'message' => 'Exam started successfully',
                'exam_id' => $exam->id,
                'expires_at' => $exam->started_at
                    ->addMinutes($exam->duration_minutes),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 409);
        }
    }

    public function show(Exam $exam)
    {
        if ($exam->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($exam->status !== 'ongoing') {
            return response()->json(['message' => 'Exam already submitted'], 400);
        }

        try {
            return response()->json(
                $this->examService->getExamQuestions($exam)
            );

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 410);
        }
    }

    public function ongoing(ExamService $service)
    {
        $exam = $service->getOngoingExam(auth()->id());

        if (!$exam) {
            return response()->json(['message' => 'No ongoing exam'], 404);
        }

        return response()->json($exam);
    }

    public function meta(Exam $exam, ExamService $service)
    {
        $this->authorize('view', $exam);

        return response()->json(
            $service->getExamMeta($exam)
        );
    }

}
