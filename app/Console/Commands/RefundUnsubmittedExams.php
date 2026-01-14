<?php

// app/Console/Commands/RefundUnsubmittedExams.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exam;
use App\Services\CBT\WalletPaymentService;

class RefundUnsubmittedExams extends Command
{
    protected $signature = 'exams:refund-unsubmitted';
    protected $description = 'Refund exam fees for unsubmitted exams due to network issues';

    protected WalletPaymentService $walletService;

    public function __construct(WalletPaymentService $walletService)
    {
        parent::__construct();
        $this->walletService = $walletService;
    }

    public function handle()
    {
        $exams = Exam::where('fee_paid', true)
            ->whereNull('submitted_at')
            ->where('fee_refunded', false)
            ->get();

        foreach ($exams as $exam) {
            $this->walletService->refundExamFee($exam);
            $this->info("Refunded exam fee for exam ID: {$exam->id}");
        }
    }
}
