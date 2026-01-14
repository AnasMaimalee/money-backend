<?php

namespace App\Services\CBT;

use App\Models\Exam;
use Illuminate\Support\Facades\DB;
use App\Repositories\CBT\ExamRepository;
use App\Services\CBT\CbtSettingService;
use App\Notifications\ExamStartedNotification;
use App\Notifications\ExamSubmittedNotification;

class ExamService
{
    public function __construct(
        protected ExamRepository $examRepository,
        protected CbtSettingService $cbtSettingService
    ) {}

    // ---------------- START EXAM ----------------
    public function startExam(string $userId, array $subjectIds): Exam
    {
        $settings = $this->cbtSettingService->getSettings();

        // English compulsory
        if (!$this->examRepository->subjectExistsBySlug($subjectIds, 'english-language')) {
            throw new \Exception('English is compulsory for JAMB CBT');
        }

        if (count($subjectIds) !== $settings->subjects_count) {
            throw new \Exception("You must select exactly {$settings->subjects_count} subjects");
        }

        if ($this->examRepository->findOngoingExam($userId)) {
            throw new \Exception('You already have an ongoing exam');
        }

        return DB::transaction(function () use ($userId, $subjectIds, $settings) {

            $exam = $this->examRepository->createExam([
                'user_id' => $userId,
                'status' => 'ongoing',
                'started_at' => now(),
                'duration_minutes' => $settings->duration_minutes,
                'total_questions' => count($subjectIds) * $settings->questions_per_subject,
            ]);

            foreach ($subjectIds as $subjectId) {
                $this->examRepository->createAttempt($exam->id, $subjectId);

                $questions = $this->examRepository->getRandomQuestions(
                    $subjectId,
                    $settings->questions_per_subject
                );

                foreach ($questions as $question) {
                    $this->examRepository->createExamAnswer(
                        $exam->id,
                        $question->id,
                        $subjectId
                    );
                }
            }

            $exam->user->notify(new ExamStartedNotification($exam->id));

            return $exam;
        });
    }

    // ---------------- GET EXAM QUESTIONS ----------------
    public function getExamQuestions(Exam $exam): array
    {
        $this->assertOwnership($exam);

        if ($exam->status !== 'ongoing') {
            throw new \Exception('Exam already submitted');
        }

        $answers = $this->examRepository->getExamQuestions($exam);

        return $answers->map(function ($answer) {
            return [
                'answer_id' => $answer->id,
                'question_id' => $answer->question->id,
                'subject' => $answer->question->subject->name,
                'question' => $answer->question->question,
                'options' => [
                    'A' => $answer->question->option_a,
                    'B' => $answer->question->option_b,
                    'C' => $answer->question->option_c,
                    'D' => $answer->question->option_d,
                ],
                'selected_option' => $answer->selected_option,
            ];
        })->values()->toArray();
    }

    // ---------------- SAVE ANSWER ----------------
    public function answerQuestion(string $examId, string $answerId, string $selectedOption)
    {
        $exam = $this->examRepository->findOngoingExam(auth()->id());
        $this->assertOwnership($exam);

        if ($exam->status !== 'ongoing') {
            abort(400, 'Exam already submitted');
        }

        $examAnswer = $this->examRepository->getAnswer($answerId);
        $examAnswer->update(['selected_option' => $selectedOption]);

        return $examAnswer;
    }

    // ---------------- SUBMIT EXAM ----------------
    public function submitExam(Exam $exam)
    {
        $this->assertOwnership($exam);

        if ($exam->status !== 'ongoing') return;

        DB::transaction(function () use ($exam) {
            $answers = $this->examRepository->getExamQuestions($exam);

            $totalScore = 0;
            foreach ($answers as $ans) {
                $correct = $ans->selected_option === $ans->question->correct_option;
                $ans->update(['is_correct' => $correct]);
                if ($correct) $totalScore++;
            }

            $exam->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'total_score' => $totalScore
            ]);

            $exam->user->notify(new ExamSubmittedNotification($exam->id));
        });
    }

    protected function assertOwnership(Exam $exam): void
    {
        if ($exam->user_id !== auth()->id()) {
            abort(403, 'Unauthorized exam access');
        }
    }
}
