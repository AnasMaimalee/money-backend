<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Models\Question;
use App\Models\ExamAnswer;
use Illuminate\Support\Facades\DB;

class AnswerService
{
    /**
     * Save or update the selected answer for a question
     */
    public function saveAnswer(Exam $exam, Question $question, string $selectedOption): ExamAnswer
    {
        // Ownership check
        if ($exam->user_id !== auth()->id()) {
            throw new \Exception('Unauthorized exam access');
        }

        // Find existing answer or create new
        $answer = ExamAnswer::firstOrNew([
            'exam_id' => $exam->id,
            'question_id' => $question->id,
        ]);

        $answer->selected_option = $selectedOption;
        $answer->save();

        return $answer;
    }

    /**
     * Get the next unanswered question in the exam
     */
    public function getNextQuestion(Exam $exam, Question $currentQuestion): ?ExamAnswer
    {
        return ExamAnswer::where('exam_id', $exam->id)
            ->whereNull('selected_option')
            ->where('id', '!=', $currentQuestion->id)
            ->orderBy('id')
            ->first();
    }
}
