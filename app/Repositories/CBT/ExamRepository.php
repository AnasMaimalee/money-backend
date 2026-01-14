<?php

namespace App\Repositories\CBT;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Str;

class ExamRepository
{
    /**
     * Find ongoing exam for a user
     */
    public function findOngoingExam(string $userId): ?Exam
    {
        return Exam::where('user_id', $userId)
            ->where('status', 'ongoing')
            ->first();
    }

    /**
     * Create a new exam
     */
    public function createExam(array $data): Exam
    {
        // ✅ UUID safety added (no breaking change)
        $data['id'] ??= (string) Str::uuid();

        return Exam::create($data);
    }

    /**
     * Create a subject attempt for an exam
     */
    public function createAttempt(string $examId, string $subjectId): void
    {
        ExamAttempt::create([
            'id' => (string) Str::uuid(), // ✅ UUID safety
            'exam_id' => $examId,
            'subject_id' => $subjectId,
            'score' => 0,
        ]);
    }

    /**
     * Get random questions for a subject
     */
    public function getRandomQuestions(string $subjectId, int $limit)
    {
        return Question::where('subject_id', $subjectId)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Create an exam answer record for a question
     */
    public function createExamAnswer(
        string $examId,
        string $questionId,
        string $subjectId
    ): void {
        ExamAnswer::create([
            'id' => (string) Str::uuid(), // ✅ UUID safety
            'exam_id' => $examId,
            'question_id' => $questionId,
            'subject_id' => $subjectId,   // ✅ FIX (THIS WAS MISSING)
            'selected_option' => null,
            'is_correct' => null,
        ]);
    }

    /**
     * Get all exam answers for an exam
     */
    public function getExamQuestions(Exam $exam)
    {
        return ExamAnswer::with('question.subject')
            ->where('exam_id', $exam->id)
            ->get();
    }

    /**
     * Get ongoing exam for a user (latest)
     */
    public function getOngoingExam(string $userId): ?Exam
    {
        return Exam::where('user_id', $userId)
            ->where('status', 'ongoing')
            ->latest()
            ->first();
    }

    /**
     * Update exam record
     */
    public function updateExam(string $examId, array $data): void
    {
        Exam::where('id', $examId)->update($data);
    }

    /**
     * Get exam metadata (subjects, duration, total questions)
     */
    public function getExamMeta(Exam $exam): array
    {
        return [
            'duration_minutes' => $exam->duration_minutes,
            'total_questions' => $exam->total_questions,
            'subjects' => $exam->attempts()
                ->with('subject:id,name')
                ->get()
                ->pluck('subject.name'),
        ];
    }

    /**
     * Check if a subject exists by slug among selected subjects
     */
    public function subjectExistsBySlug(array $subjectIds, string $slug): bool
    {
        return Subject::whereIn('id', $subjectIds)
            ->where('slug', $slug)
            ->exists();
    }

    public function getAnswer(string $answerId)
    {
        return ExamAnswer::findOrFail($answerId);
    }

}
