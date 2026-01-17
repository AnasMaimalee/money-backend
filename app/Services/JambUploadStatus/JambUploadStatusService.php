<?php

namespace App\Services\JambUploadStatus;

use App\Mail\JambUploadStatusCompletedMail;
use App\Mail\JambUploadStatusRejectedMail;
use App\Mail\WalletDebited;
use App\Models\User;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Repositories\JambUploadStatus\JambUploadStatusRepository;
use App\Services\WalletService;

class JambUploadStatusService
{
    public function __construct(
        protected JambUploadStatusRepository $repo,
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
            ->whereRaw('LOWER(name) = ?', [strtolower('JAMB Upload Status')])
            ->firstOrFail();

        // Balance check
        if ($user->wallet->balance < $service->customer_price) {
            abort(422, 'Insufficient wallet balance');
        }

        $superAdmin = User::role('superadmin')->first();
        if (! $superAdmin) {
            abort(500, 'Super admin not configured');
        }

        // Base reference (for logical grouping only)
        $baseReference = 'jamb_upload_status_' . Str::uuid();

        $debitTransaction = null;

        DB::transaction(function () use (
            $user,
            $data,
            $service,
            $superAdmin,
            $baseReference,
            &$debitTransaction
        ) {
            // ✅ UNIQUE references per wallet action
            $debitReference  = $baseReference . '_debit';
            $creditReference = $baseReference . '_credit';

            // 1️⃣ Debit user
            $debitTransaction = $this->walletService->debitUser(
                $user,
                $service->customer_price,
                'Purchase: JAMB Upload Status Check',
                $debitReference
            );

            // 2️⃣ Credit platform (superadmin)
            $this->walletService->creditUser(
                $superAdmin,
                $service->customer_price,
                'Payment received: JAMB Upload Status (User: ' . $user->name . ')',
                $creditReference
            );

            // 3️⃣ Create request
            $this->repo->create([
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

        // 📧 Debit email
        Mail::to($user->email)->send(
            new WalletDebited(
                user: $user,
                amount: $service->customer_price,
                balance: $debitTransaction->balance_after,
                reason: 'Purchase: JAMB Upload Status Check'
            )
        );

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
        if (! auth()->user()->hasRole('administrator')) {
            abort(403, 'Unauthorized action');
        }

        return DB::transaction(function () use ($id, $filePath, $admin) {
            $job = $this->repo->find($id);

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
                new JambUploadStatusCompletedMail($job)
            );

            return [
                'message' => 'Job completed and awaiting superadmin approval',
                'job' => [
                    'id'               => $job->id,
                    'status'           => $job->status,
                    'user'             => [
                        'name'  => $job->user->name,
                        'email' => $job->user->email,
                    ],
                    'service'          => $job->service->name,
                    'completed_by'     => $job->completedBy->name,
                    'result_file_url'  => asset('storage/' . $job->result_file),
                    'created_at'       => $job->created_at,
                ],
            ];
        });
    }

    /**
     * ======================
     * SUPER ADMIN
     * ======================
     */

    public function approve(string $id, User $superAdmin)
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403, 'Unauthorized financial action');
        }

        return DB::transaction(function () use ($id, $superAdmin) {
            $job = $this->repo->find($id);

            if ($job->status !== 'completed') {
                abort(422, 'Job is not ready for approval');
            }

            if ($job->status === 'approved') {
                abort(422, 'Job already approved');
            }

            if (!$job->completedBy) {
                abort(422, 'Administrator who completed the job not found');
            }

            // ✅ FIXED: DEBIT SUPERADMIN WALLET → CREDIT ADMIN
            $this->walletService->adminCreditUser(
                $superAdmin,                          // Superadmin (verification ONLY)
                $job->completedBy,                   // Admin who gets PAID
                $job->admin_payout,                  // PAYOUT AMOUNT
                'Payment for JAMB Upload Status service (Request #' . $job->id . ')'
            );

            $job->update([
                'status'          => 'approved',
                'approved_by'     => $superAdmin->id,
                'is_paid'         => true,
                'platform_profit' => $job->customer_price - $job->admin_payout,
            ]);

            return [
                'message'         => 'Job approved and administrator paid from superadmin wallet',
                'job_id'          => $job->id,
                'admin_paid'      => $job->admin_payout,
                'platform_profit' => $job->platform_profit,
                'wallet_flow'     => 'SuperAdmin → Admin'
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

            if (!in_array($job->status, ['completed', 'processing'])) {
                abort(422, 'Job cannot be rejected at this stage');
            }

            // Refund user
            $this->walletService->transfer(
                $superAdmin,
                $job->user,
                $job->customer_price,
                'Refund: Rejected JAMB Upload Status request (Reason: ' . $reason . ')'
            );

            $job->update([
                'status'           => 'rejected',
                'rejected_by'      => $superAdmin->id,
                'rejection_reason' => $reason,
            ]);

            Mail::to($job->email)->send(
                new JambUploadStatusRejectedMail($job)
            );

            if ($job->completedBy) {
                Mail::to($job->completedBy->email)->send(
                    new JambUploadStatusRejectedMail($job)
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
