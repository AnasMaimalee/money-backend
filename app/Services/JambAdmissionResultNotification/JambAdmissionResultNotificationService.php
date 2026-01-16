<?php

namespace App\Services\JambAdmissionResultNotification;

use App\Mail\JambAdmissionResultNotificationCompletedMail;
use App\Mail\JambAdmissionResultNotificationRejectedMail;
use App\Mail\WalletDebited;
use App\Models\User;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Repositories\JambAdmissionResultNotification\JambAdmissionResultNotificationRepository;
use App\Services\WalletService;

class JambAdmissionResultNotificationService
{
    public function __construct(
        protected JambAdmissionResultNotificationRepository $repo,
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
            ->whereRaw('LOWER(name) = ?', [strtolower('JAMB Results Notifications')])
            ->firstOrFail();

        if ($user->wallet->balance < $service->customer_price) {
            abort(422, 'Insufficient wallet balance');
        }

        $superAdmin = User::role('superadmin')->firstOrFail();

        // Immutable unique group reference
        $groupReference = 'jamb_result_notification_' . Str::uuid();

        $debitTransaction = null;

        $createdRequest = DB::transaction(function () use ($user, $data, $service, $superAdmin, $groupReference, &$debitTransaction) {
            // Debit user → credit platform
            $debitTransaction = $this->walletService->servicePayment(
                customer: $user,
                platform: $superAdmin,
                amount: $service->customer_price,
                description: 'Purchase: JAMB Result Release Notification',
                groupReference: $groupReference
            )['debit_transaction'];

            // Store request securely
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

        // Notify user of debit
        Mail::to($user->email)->send(
            new WalletDebited(
                user: $user,
                amount: $service->customer_price,
                balance: $debitTransaction->balance_after,
                reason: 'Purchase: JAMB Result Release Notification'
            )
        );

        return response()->json([
            'success' => true,
            'message' => 'Your request has been successfully submitted.',
        ], 201);
    }

    /**
     * ======================
     * ADMIN
     * ======================
     */

    public function pending()
    {
        $this->checkRole('administrator');
        return $this->repo->pending();
    }

    public function take(string $id, User $admin)
    {
        $this->checkRole('administrator');
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
        $this->checkRole('administrator');

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
                new JambAdmissionResultNotificationCompletedMail($job)
            );

            return [
                'message' => 'Notification setup completed and awaiting superadmin approval',
                'job' => [
                    'id'               => $job->id,
                    'status'           => $job->status,
                    'user'             => ['name' => $job->user->name, 'email' => $job->user->email],
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
     * SUPERADMIN
     * ======================
     */

    public function approve(string $id, User $superAdmin)
    {
        $this->checkRole('superadmin');

        return DB::transaction(function () use ($id, $superAdmin) {
            $job = $this->repo->find($id);

            if ($job->status !== 'completed') abort(422, 'Job not ready for approval');
            if ($job->is_paid) abort(409, 'Job already paid');
            if (!$job->completedBy) abort(422, 'Admin not found for this job');

            $this->walletService->adminCreditUser(
                $superAdmin,
                $job->completedBy,
                $job->admin_payout,
                'Payment for JAMB Result Notification (Request #' . $job->id . ')'
            );

            $job->update([
                'status'          => 'approved',
                'is_paid'         => true,
                'approved_by'     => $superAdmin->id,
                'platform_profit' => $job->customer_price - $job->admin_payout,
            ]);

            return [
                'message'         => 'Job approved and admin paid from superadmin wallet',
                'job_id'          => $job->id,
                'admin_paid'      => $job->admin_payout,
                'platform_profit' => $job->platform_profit,
                'wallet_flow'     => 'SuperAdmin → Admin',
            ];
        });
    }

    public function reject(string $id, string $reason, User $superAdmin)
    {
        $this->checkRole('superadmin');

        return DB::transaction(function () use ($id, $reason, $superAdmin) {
            $job = $this->repo->find($id);

            if ($job->status === 'rejected') abort(422, 'Job already rejected');
            if (!in_array($job->status, ['completed', 'processing'])) {
                abort(422, 'Job cannot be rejected at this stage');
            }

            $this->walletService->transfer(
                $superAdmin,
                $job->user,
                $job->customer_price,
                'Refund: Rejected JAMB Result Notification (Reason: ' . $reason . ')'
            );

            $job->update([
                'status'           => 'rejected',
                'rejected_by'      => $superAdmin->id,
                'rejection_reason' => $reason,
            ]);

            // Emails
            Mail::to($job->email)->send(
                new JambAdmissionResultNotificationRejectedMail($job)
            );

            if ($job->completedBy) {
                Mail::to($job->completedBy->email)->send(
                    new JambAdmissionResultNotificationRejectedMail($job)
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
        $this->checkRole('superadmin');
        return $this->repo->allWithRelations();
    }

    /**
     * ======================
     * PRIVATE HELPERS
     * ======================
     */
    private function checkRole(string $role)
    {
        if (! auth()->user()->hasRole($role)) {
            abort(403, 'Unauthorized action');
        }
    }
}
