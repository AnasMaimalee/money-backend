<?php

namespace App\Services\CBT;

use App\Models\Exam;
use Illuminate\Support\Facades\DB;
use App\Repositories\CBT\ExamRepository;
use App\Services\CBT\CbtSettingService;
use App\Notifications\ExamStartedNotification;
use App\Notifications\ExamSubmittedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

class ExamService
{
    public function __construct(
        protected ExamRepository $examRepository,
        protected CbtSettingService $cbtSettingService
    ) {}

    /* =====================================================
     | START EXAM
     ===================================================== */
    public function startExam(string $userId, array $subjectIds): Exam
    {
        $settings = $this->cbtSettingService->getSettings();

        $this->validateSubjectSelection($subjectIds, $settings->subjects_count);

        if ($this->examRepository->findOngoingExam($userId)) {
            throw new RuntimeException('You already have an ongoing exam');
        }

        return DB::transaction(function () use ($userId, $subjectIds, $settings) {
            $exam = $this->examRepository->createExam([
                'user_id' => $userId,
                'status' => 'ongoing',
                'started_at' => now(),
                'ends_at' => now()->addMinutes($settings->duration_minutes),
                'duration_minutes' => $settings->duration_minutes,
                'total_questions' => count($subjectIds) * $settings->questions_per_subject,
            ]);


            foreach ($subjectIds as $subjectId) {
                $this->seedSubjectQuestions(
                    exam: $exam,
                    subjectId: $subjectId,
                    limit: $settings->questions_per_subject
                );
            }

            $exam->user->notify(new ExamStartedNotification($exam->id));

            return $exam;
        });
    }

    /* =====================================================
     | GET EXAM QUESTIONS
     ===================================================== */
    public function getExamQuestions(Exam $exam): array
    {
        $this->authorizeExam($exam);
        $this->ensureExamIsOngoing($exam);

        return $this->examRepository
            ->getExamQuestions($exam)
            ->map(fn ($answer) => $this->transformQuestion($answer))
            ->values()
            ->toArray();
    }

    /* =====================================================
     | SAVE ANSWER
     ===================================================== */
    public function answerQuestion(
        Exam $exam,
        string $answerId,
        string $selectedOption
    ) {
        $this->authorizeExam($exam);
        $this->ensureExamIsOngoing($exam);

        $answer = $this->examRepository->getAnswerForExam(
            examId: $exam->id,
            answerId: $answerId
        );

        $answer->update([
            'selected_option' => $selectedOption
        ]);

        return $answer;
    }

    /* =====================================================
     | SUBMIT EXAM
     ===================================================== */
    public function submitExam(Exam $exam): void
    {
        $this->authorizeExam($exam);

        if ($exam->status !== 'ongoing') {
            return;
        }

        DB::transaction(function () use ($exam) {
            $answers = $this->examRepository->getExamQuestions($exam);

            $score = 0;

            foreach ($answers as $answer) {
                $correct = $answer->selected_option === $answer->question->correct_option;

                $answer->update(['is_correct' => $correct]);

                if ($correct) {
                    $score++;
                }
            }

            $exam->update([
                'status'        => 'submitted',
                'submitted_at'  => now(),
                'total_score'   => $score
            ]);

            $exam->user->notify(new ExamSubmittedNotification($exam->id));
        });
    }

    /* =====================================================
     | INTERNAL HELPERS
     ===================================================== */

    protected function validateSubjectSelection(array $subjectIds, int $requiredCount): void
    {
        if (
            !$this->examRepository->subjectExistsBySlug($subjectIds, 'english-language')
        ) {
            throw new RuntimeException('English is compulsory for JAMB CBT');
        }

        if (count($subjectIds) !== $requiredCount) {
            throw new RuntimeException(
                "You must select exactly {$requiredCount} subjects"
            );
        }
    }

    protected function seedSubjectQuestions(
        Exam $exam,
        string $subjectId,
        int $limit
    ): void {
        $this->examRepository->createAttempt($exam->id, $subjectId);

        $questions = $this->examRepository
            ->getRandomQuestions($subjectId, $limit);

        foreach ($questions as $question) {
            $this->examRepository->createExamAnswer(
                $exam->id,
                $question->id,
                $subjectId
            );
        }
    }

    protected function transformQuestion($answer): array
    {
        $q = $answer->question;

        return [
            'answer_id'       => $answer->id,
            'question_id'     => $q->id,
            'subject'         => $q->subject->name,
            'question'        => $q->question,
            'options'         => [
                'A' => $q->option_a,
                'B' => $q->option_b,
                'C' => $q->option_c,
                'D' => $q->option_d,
            ],
            'selected_option' => $answer->selected_option,
        ];
    }

    protected function authorizeExam(Exam $exam): void
    {
        if ($exam->user_id !== auth()->id()) {
            throw new AuthorizationException('Unauthorized exam access');
        }
    }

    protected function ensureExamIsOngoing(Exam $exam): void
    {
        if ($exam->status !== 'ongoing') {
            throw new RuntimeException('Exam already submitted');
        }
    }
}
