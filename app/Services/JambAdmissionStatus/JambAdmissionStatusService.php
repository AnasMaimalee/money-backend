<?php

namespace App\Services\JambAdmissionStatus;

use App\Mail\JambAdmissionStatusCompletedMail;
use App\Mail\JambAdmissionStatusRejectedMail;
use App\Mail\WalletDebited;
use App\Models\User;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Repositories\JambAdmissionStatus\JambAdmissionStatusRepository;
use App\Services\WalletService;

class JambAdmissionStatusService
{
    public function __construct(
        protected JambAdmissionStatusRepository $repo,
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
            ->whereRaw('LOWER(name) = ?', [strtolower('Checking Admission Status')])
            ->firstOrFail();

        // Balance check
        if ($user->wallet->balance < $service->customer_price) {
            abort(422, 'Insufficient wallet balance');
        }

        $superAdmin = User::role('superadmin')->first();

        if (! $superAdmin) {
            abort(500, 'Super admin not configured');
        }

        // Unique group reference for linking debit + credit
        $groupReference = 'jamb_admission_status_' . Str::uuid();

        // Capture debit transaction for email
        $debitTransaction = null;
        $createdRequest = null;

        $createdRequest = DB::transaction(function () use (
            $user, $data, $service, $superAdmin, $groupReference, &$debitTransaction
        ) {
            // 1. Debit user wallet
            $debitTransaction = $this->walletService->debitUser(
                $user,
                $service->customer_price,
                'Purchase: JAMB Admission Status Check',
                $groupReference
            );

            // 2. Credit platform (superadmin wallet)
            $this->walletService->creditUser(
                $superAdmin,
                $service->customer_price,
                'Payment received: JAMB Admission Status (User: ' . $user->name . ')',
                $groupReference
            );

            // 3. Create the request
            return $this->repo->create([
                'user_id'             => $user->id,
                'service_id'          => $service->id,
                'email'               => $data['email'],
                'phone_number'        => $data['phone_number'] ?? null,
                'profile_code'        => $data['profile_code'],
                'registration_number' => $data['registration_number'] ?? null,
                'customer_price'      => $service->customer_price,
                'admin_payout'        => $service->admin_payout,
                'platform_profit'     => $service->customer_price - $service->admin_payout, // ← ADD THIS
                'status'              => 'pending',
                'is_paid'             => false,
            ]);
        });

        // Send debit confirmation email to user (after successful transaction)
        Mail::to($user->email)->send(
            new WalletDebited(
                user: $user,
                amount: $service->customer_price,
                balance: $debitTransaction->balance_after,
                reason: 'Purchase: JAMB Admission Status Check'
            )
        );

        return $createdRequest;
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
                'status'       => 'completed_by_admin',
                'result_file'  => $filePath,
                'completed_by' => $admin->id,
            ]);

            $job->load(['user', 'service', 'completedBy']);

            // Notify user that result is ready (awaiting approval)
            Mail::to($job->email)->send(
                new JambAdmissionStatusCompletedMail($job)
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

            // Pay the admin
            $this->walletService->creditUser(
                $job->completedBy,
                $job->admin_payout,
                'Payment for JAMB Admission Status service (Request #' . $job->id . ')'
            );

            $job->update([
                'status'          => 'approved',
                'approved_by'     => $superAdmin->id,
                'platform_profit' => $job->customer_price - $job->admin_payout,
            ]);

            return [
                'message'         => 'Job approved and administrator paid successfully',
                'job_id'          => $job->id,
                'admin_paid'      => $job->admin_payout,
                'platform_profit' => $job->platform_profit,
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

            // Refund user from platform wallet
            $this->walletService->transfer(
                $superAdmin,
                $job->user,
                $job->customer_price,
                'Refund: Rejected JAMB Admission Status request (Reason: ' . $reason . ')'
            );

            $job->update([
                'status'           => 'rejected',
                'rejected_by'      => $superAdmin->id,
                'rejection_reason' => $reason,
            ]);

            // Notify user
            Mail::to($job->email)->send(
                new JambAdmissionStatusRejectedMail($job)
            );

            // Notify admin (optional but recommended)
            if ($job->completedBy) {
                Mail::to($job->completedBy->email)->send(
                    new JambAdmissionStatusRejectedMail($job)
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
