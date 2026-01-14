<?php

namespace App\Services\CBT;

use App\Models\Exam;
use App\Repositories\CBT\PaymentRepository;

class PaymentService
{
    public function __construct(
        protected PaymentRepository $repository
    ) {}

    public function initiatePayment(string $userId, Exam $exam, float $amount, string $provider): Payment
    {
        // Check if payment already exists
        $existing = $this->repository->findPendingPayment($userId, $exam->id);

        if ($existing) {
            return $existing;
        }

        // Create new pending payment
        return $this->repository->createPayment([
            'user_id' => $userId,
            'exam_id' => $exam->id,
            'amount' => $amount,
            'status' => 'pending',
            'payment_provider' => $provider,
            'reference' => null
        ]);
    }

    public function confirmPayment(string $paymentId, string $reference)
    {
        $this->repository->markAsPaid($paymentId, $reference);
    }
}
