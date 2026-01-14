<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Repositories\CBT\SubmitExamRepository;
use App\Notifications\ExamSubmittedNotification;
use App\Notifications\ResultReadyNotification;
use Illuminate\Support\Facades\DB;

class SubmitExamService
{
    public function __construct(
        protected SubmitExamRepository $submitExamRepository
    ) {}

    public function submit(Exam $exam, bool $isAuto = false): array
    {
        // ✅ Ownership check ONLY for manual submit
        if (!$isAuto && $exam->user_id !== auth()->id()) {
            throw new \Exception('Unauthorized exam access');
        }

        if ($exam->status === 'submitted') {
            throw new \Exception('Exam already submitted');
        }

        return DB::transaction(function () use ($exam) {

            $answers = $this->submitExamRepository
                ->getExamAnswers($exam->id);

            /**
             * Group answers by subject
             */
            $subjectScores = [];

            foreach ($answers as $answer) {
                $subjectId = $answer->question->subject_id;

                if (!isset($subjectScores[$subjectId])) {
                    $subjectScores[$subjectId] = 0;
                }

                if ($answer->is_correct === true) {
                    $subjectScores[$subjectId]++;
                }
            }

            /**
             * Save scores per subject
             */
            foreach ($subjectScores as $subjectId => $score) {
                $this->submitExamRepository
                    ->updateAttemptScore($exam->id, $subjectId, $score);
            }

            /**
             * Finalize exam
             */
            $this->submitExamRepository->submitExam($exam);

            // ✅ Notifications (SAFE PLACE)
            $exam->user->notify(
                new ExamSubmittedNotification($exam->id)
            );

            $exam->user->notify(
                new ResultReadyNotification($exam->id)
            );

            return [
                'exam_id' => $exam->id,
                'total_questions' => $exam->total_questions,
                'scores' => $subjectScores,
            ];
        });
    }
}
