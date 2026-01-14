<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Services\CBT\AnswerService;
use Illuminate\Http\Request;

class AnswerController extends Controller
{
    public function __construct(
        protected AnswerService $service
    ) {}

    /**
     * Save answer and return next question (CBT friendly)
     */
    public function save(Request $request, Exam $exam, Question $question)
    {
        $request->validate([
            'selected_option' => 'required|in:A,B,C,D'
        ]);

        try {
            // Save the current answer
            $this->service->saveAnswer(
                $exam,
                $question,
                $request->selected_option
            );

            // Fetch next unanswered question
            $nextAnswer = $this->service->getNextQuestion($exam, $question);

            // Build response for frontend
            $response = [
                'message' => 'Answer saved',
                'next_question' => null,
                'exam_completed' => false
            ];

            if ($nextAnswer) {
                $q = $nextAnswer->question;
                $response['next_question'] = [
                    'answer_id' => $nextAnswer->id,
                    'question_id' => $q->id,
                    'subject' => $q->subject->name,
                    'question' => $q->question,
                    'options' => [
                        'A' => $q->option_a,
                        'B' => $q->option_b,
                        'C' => $q->option_c,
                        'D' => $q->option_d,
                    ],
                ];
            } else {
                // Exam completed
                $response['exam_completed'] = true;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
