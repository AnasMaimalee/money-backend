<?php

namespace App\Services\JambResult;

use App\Models\User;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\WalletDebited;
use App\Mail\JambResultServiceCompletedMail;
use App\Mail\JambResultServiceRejectionMail;
use App\Repositories\JambResult\JambResultRepository;
use App\Services\WalletService;

class JambResultService
{
    public function __construct(
        protected JambResultRepository $repo,
        protected WalletService $walletService
    ) {}

    /**
     * Get user's own JAMB result requests
     */
    public function my(User $user)
    {
        return $this->repo->userRequests($user->id);
    }

    /**
     * User submits a new JAMB Result request
     */
    public function submit(User $user, array $data)
    {
        $service = Service::where('active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower('Jamb Original Result')])
            ->firstOrFail();

        // Check balance
        if ($user->wallet->balance < $service->customer_price) {
            abort(422, 'Insufficient wallet balance');
        }

        $superAdmin = User::role('superadmin')->first();

        if (! $superAdmin) {
            abort(500, 'Super admin not configured');
        }

        // Unique group reference to link debit + credit
        $groupReference = 'jamb_result_' . Str::uuid();

        // We'll capture the debit transaction to use in email
        $debitTransaction = null;
        $createdRequest = null;

        $createdRequest = DB::transaction(function () use (
            $user, $data, $service, $superAdmin, $groupReference, &$debitTransaction
        ) {
            // 1. Debit user wallet
            $debitTransaction = $this->walletService->debitUser(
                $user,
                $service->customer_price,
                'Purchase: JAMB Original Result',
                $groupReference
            );

            // 2. Credit superadmin (platform receives payment)
            $this->walletService->creditUser(
                $superAdmin,
                $service->customer_price,
                'Payment received: JAMB Original Result (User: ' . $user->name . ')',
                $groupReference
            );

            // 3. Create the request
            return $this->repo->create([
                'user_id'             => $user->id,
                'service_id'          => $service->id,
                'email'               => $data['email'],
                'phone_number'        => $data['phone_number'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'customer_price'      => $service->customer_price,
                'admin_payout'        => $service->admin_payout,
                'status'              => 'pending',
                'is_paid'             => false,
            ]);
        });

        Mail::to($user->email)->send(
            new WalletDebited(
                $user,
                $service->customer_price,
                $debitTransaction->balance_after,
                'Purchase: JAMB Original Result'
            )
        );

        return $createdRequest;
    }

    /**
     * Admin takes a pending job
     */
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

    /**
     * Admin completes the job by uploading result file
     */
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

            // Notify user that result is ready (awaiting approval)
            Mail::to($job->email)->send(
                new JambResultServiceCompletedMail($job)
            );

            return [
                'message' => 'Job completed and awaiting superadmin approval',
                'job' => [
                    'id'              => $job->id,
                    'status'          => $job->status,
                    'user'            => [
                        'name'  => $job->user->name,
                        'email' => $job->user->email,
                    ],
                    'service'         => $job->service->name,
                    'completed_by'    => $job->completedBy->name,
                    'result_file_url' => asset('storage/' . $job->result_file),
                    'created_at'      => $job->created_at,
                ],
            ];
        });
    }

    /**
     * Superadmin approves job and pays admin
     */
    public function approve(string $id, User $superAdmin)
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403, 'Unauthorized financial action');
        }

        return DB::transaction(function () use ($id, $superAdmin) {
            $job = $this->repo->find($id);

            if ($job->status !== 'completed_by_admin') {
                abort(422, 'Job is not ready for approval');
            }

            if ($job->status === 'approved') {
                abort(422, 'Job already approved');
            }

            // Pay the admin
            $this->walletService->creditUser(
                $job->completedBy,
                $job->admin_payout,
                'Payment for completed JAMB Result service (Request #' . $job->id . ')'
            );

            $job->update([
                'status'          => 'approved',
                'platform_profit' => $job->customer_price - $job->admin_payout,
                'approved_by'     => $superAdmin->id,
            ]);

            return [
                'message'         => 'Job approved and administrator paid successfully',
                'job_id'          => $job->id,
                'admin_paid'      => $job->admin_payout,
                'platform_profit' => $job->platform_profit,
            ];
        });
    }

    /**
     * Superadmin rejects job and refunds user
     */
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

            // Refund user from platform (superadmin wallet)
            $this->walletService->transfer(
                $superAdmin,
                $job->user,
                $job->customer_price,
                'Refund: Rejected JAMB Result request (Reason: ' . $reason . ')'
            );

            $job->update([
                'status'           => 'rejected',
                'rejected_by'      => $superAdmin->id,
                'rejection_reason' => $reason,
            ]);

            // Notify user
            Mail::to($job->email)->send(
                new JambResultServiceRejectionMail($job)
            );

            // Notify admin who worked on it
            if ($job->completedBy) {
                Mail::to($job->completedBy->email)->send(
                    new JambResultServiceRejectionMail($job)
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

    /**
     * Get all pending jobs (for administrators)
     */
    public function pending()
    {
        if (! auth()->user()->hasRole('administrator')) {
            abort(403, 'Unauthorized action');
        }

        return $this->repo->pendingRequests();
    }

    /**
     * Get all jobs with relations (for superadmin)
     */
    public function all()
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403, 'Unauthorized action');
        }

        return $this->repo->allWithRelations();
    }
}
