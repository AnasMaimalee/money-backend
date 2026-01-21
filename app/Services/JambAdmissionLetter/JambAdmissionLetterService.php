<?php

namespace App\Services\JambAdmissionLetter;

use App\Mail\JambAdmissionLetterCompletedMail;
use App\Mail\JambAdmissionLetterRejectedMail;
use App\Mail\WalletDebited;
use App\Models\User;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Repositories\JambAdmissionLetter\JambAdmissionLetterRepository;
use App\Services\WalletService;
use App\Models\JambAdmissionLetterRequest;
class JambAdmissionLetterService
{
    public function __construct(
        protected JambAdmissionLetterRepository $repo,
        protected WalletService $walletService
    ) {}

    /**
     * ======================
     * USER
     * ======================
     */

    public function my(User $user)
    {
        return $this->repo->userRequests($user->id);
    }

    public function submit(User $user, array $data)
    {
        $service = Service::where('active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower('Jamb Admission Letter')])
            ->firstOrFail();

        // Balance check
        if ($user->wallet->balance < $service->customer_price) {
            abort(422, 'Insufficient wallet balance');
        }

        $superAdmin = User::role('superadmin')->first();
        if (!$superAdmin) {
            abort(500, 'Super admin not configured');
        }

        $groupReference = 'jamb_admission_' . Str::uuid();

        // Capture transaction for email
        $transactions = null;

        $createdRequest = DB::transaction(function () use ($user, $data, $service, $superAdmin, $groupReference, &$transactions) {

            // ✅ SINGLE SERVICE PAYMENT
            $transactions = $this->walletService->servicePayment(
                customer: $user,
                platform: $superAdmin,
                amount: $service->customer_price,
                description: 'Purchase: JAMB Admission Letter',
                groupReference: $groupReference
            );

            // Create the request
            return $this->repo->create([
                'user_id'             => $user->id,
                'service_id'          => $service->id,
                'email'               => $data['email'],
                'phone_number'        => $data['phone_number'] ?? null,
                'profile_code'        => $data['profile_code'],
                'registration_number' => $data['registration_number'] ?? null,
                'customer_price'      => $service->customer_price,
                'admin_payout'        => $service->admin_payout,
                'platform_profit'     => $service->customer_price - $service->admin_payout,
                'status'              => 'pending',
                'is_paid'             => false,
            ]);
        });

        // Send debit confirmation email to user
        if ($transactions && $transactions['debit_transaction']) {
            Mail::to($user->email)->send(
                new WalletDebited(
                    user: $user,
                    amount: $service->customer_price,
                    balance: $transactions['debit_transaction']->balance_after,
                    reason: 'Purchase: JAMB Admission Letter'
                )
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Your work has been successfully submitted.',
        ], 201);
    }


    /**
     * ======================
     * ADMIN
     * ======================
     */

    public function pending()
    {
        if (! auth()->user()->hasRole('administrator')) {
            abort(403, 'Unauthorized action');
        }

        return $this->repo->pending();
    }

    public function take(string $id, User $admin)
    {
        if (! auth()->user()->hasRole('administrator')) {
            abort(403, 'Unauthorized action');
        }

        $job = $this->repo->find($id);

        if ($job->status !== 'pending') {
            abort(422, 'Job is no longer available');
        }

        $job->update([
            'status'   => 'processing',
            'taken_by' => $admin->id,
        ]);

        return $job;
    }

    public function complete(string $id, string $filePath, User $admin)
    {
        if (! $admin->hasRole('administrator')) {
            abort(403, 'Unauthorized action');
        }

        return DB::transaction(function () use ($id, $filePath, $admin) {

            $job = $this->repo->findOrFail($id);

            if ($job->taken_by !== $admin->id) {
                abort(403, 'You did not take this job');
            }

            if ($job->status !== 'processing') {
                abort(422, 'Job is not in processing state');
            }

            $job->update([
                'status'       => 'completed',
                'result_file'  => $filePath,
                'completed_by' => $admin->id,
            ]);

            $job->load(['user', 'service', 'completedBy']);

            Mail::to($job->email)->send(
                new JambAdmissionLetterCompletedMail($job)
            );

            return $job; // ✅ RETURN MODEL
        });
    }


    /**
     * ======================
     * SUPER ADMIN
     * ======================
     */

    // ✅ SERVICE - FIXED (Replace your approve method completely)
    public function approve(string $id, User $superAdmin)
    {
        if (! $superAdmin->hasRole('superadmin')) {
            abort(403, 'Unauthorized financial action');
        }

        return DB::transaction(function () use ($id, $superAdmin) {
            $job = JambAdmissionLetterRequest::where('id', $id)
                ->lockForUpdate() // 🔒 CRITICAL
                ->firstOrFail();

            if ($job->status !== 'completed') {
                abort(422, 'Job is not ready for approval');
            }

            if ($job->is_paid) {
                abort(409, 'Job already paid');
            }

            if (! $job->completedBy) {
                abort(422, 'Admin not found');
            }

            $groupRef = 'adm_letter_payout_' . $job->id;

            // 🔒 PAY ADMIN FROM SUPERADMIN
            $this->walletService->adminCreditUser(
                $superAdmin,
                $job->completedBy,
                $job->admin_payout,
                'Payment for JAMB Admission Letter #' . $job->id
            );

            $job->update([
                'status'          => 'approved',
                'is_paid'         => true,
                'approved_by'     => $superAdmin->id,
                'platform_profit' => $job->customer_price - $job->admin_payout,
            ]);

            return [
                'message' => 'Approved & admin paid securely',
                'job_id'  => $job->id,
            ];
        });
    }


    public function reject(string $id, string $reason, User $superAdmin)
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403, 'Unauthorized financial action');
        }

        return DB::transaction(function () use ($id, $reason, $superAdmin) {
            $job = $this->repo->find($id);

            if ($job->status === 'rejected') {
                abort(422, 'Job already rejected');
            }

            if (!in_array($job->status, ['completed_by_admin', 'processing'])) {
                abort(422, 'Job cannot be rejected at this stage');
            }

            // Refund user
            $this->walletService->transfer(
                $superAdmin,
                $job->user,
                $job->customer_price,
                'Refund: Rejected JAMB Admission Letter request (Reason: ' . $reason . ')'
            );

            $job->update([
                'status'           => 'rejected',
                'rejected_by'      => $superAdmin->id,
                'rejection_reason' => $reason,
            ]);

            Mail::to($job->email)->send(
                new JambAdmissionLetterRejectedMail($job)
            );

            if ($job->completedBy) {
                Mail::to($job->completedBy->email)->send(
                    new JambAdmissionLetterRejectedMail($job)
                );
            }

            return [
                'message'       => 'Job rejected and user fully refunded',
                'job_id'        => $job->id,
                'refund_amount' => $job->customer_price,
                'reason'        => $reason,
            ];
        });
    }

    public function all()
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403, 'Unauthorized action');
        }

        return $this->repo->allWithRelations();
    }
}
