<?php

namespace App\Http\Controllers\Api\CBT\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\CBT\SuperAdmin\QuestionBankService;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    public function __construct(
        protected QuestionBankService $service
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['subject_id', 'search']);
        $perPage = $request->get('per_page', 20);

        return response()->json(
            $this->service->listQuestions($filters, $perPage)
        );
    }

    public function preview(string $questionId)
    {
        try {
            $question = $this->service->previewQuestion($questionId);

            return response()->json([
                'question_id' => $question->id,
                'subject' => $question->subject->name,
                'question' => $question->question,
                'options' => [
                    'A' => $question->option_a,
                    'B' => $question->option_b,
                    'C' => $question->option_c,
                    'D' => $question->option_d
                ],
                'correct_option' => $question->correct_option
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Question not found'
            ], 404);
        }
    }
}
