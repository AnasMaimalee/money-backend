<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exam;
use App\Services\CBT\ExamService;
use Illuminate\Support\Facades\Log;

class AutoSubmitExpiredExams extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cbt:auto-submit-expired-exams';

    /**
     * The console command description.
     */
    protected $description = 'Automatically submit all expired ongoing CBT exams';

    /**
     * Execute the console command.
     */
    public function handle(ExamService $examService): int
    {
        $this->info('Checking for expired CBT exams...');

        Exam::where('status', 'ongoing')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->chunkById(50, function ($exams) use ($examService) {

                foreach ($exams as $exam) {
                    try {
                        $examService->submitExam($exam);

                        Log::info('CBT exam auto-submitted', [
                            'exam_id' => $exam->id,
                            'user_id' => $exam->user_id,
                        ]);

                        $this->info("Exam {$exam->id} auto-submitted");

                    } catch (\Throwable $e) {
                        Log::error('Failed to auto-submit CBT exam', [
                            'exam_id' => $exam->id,
                            'error' => $e->getMessage(),
                        ]);

                        $this->error("Failed submitting exam {$exam->id}");
                    }
                }
            });

        $this->info('Expired CBT exam check completed.');

        return Command::SUCCESS;
    }
}
