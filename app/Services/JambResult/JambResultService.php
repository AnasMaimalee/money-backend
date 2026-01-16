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
            ->whereRaw('LOWER(name) = ?', [strtolower('Jamb Original Result')])
            ->firstOrFail();

        if ($user->wallet->balance < $service->customer_price) {
            abort(422, 'Insufficient wallet balance');
        }

        $superAdmin = User::role('superadmin')->first();
        if (! $superAdmin) abort(500, 'Super admin not configured');

        $groupReference = 'jamb_result_' . Str::uuid();
        $transactions = null;

        $createdRequest = DB::transaction(function () use ($user, $data, $service, $superAdmin, $groupReference, &$transactions) {
            // Atomic debit from user and credit to platform
            $transactions = $this->walletService->servicePayment(
                customer: $user,
                platform: $superAdmin,
                amount: $service->customer_price,
                description: 'Purchase: JAMB Original Result',
                groupReference: $groupReference
            );

            return $this->repo->create([
                'user_id'             => $user->id,
                'service_id'          => $service->id,
                'email'               => $data['email'],
                'profile_code'        => $data['profile_code'],
                'phone_number'        => $data['phone_number'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'customer_price'      => $service->customer_price,
                'admin_payout'        => $service->admin_payout,
                'status'              => 'pending',
                'is_paid'             => false,
            ]);
        });

        // Send debit confirmation to user
        if ($transactions && $transactions['debit_transaction']) {
            Mail::to($user->email)->send(
                new WalletDebited(
                    $user,
                    $service->customer_price,
                    $transactions['debit_transaction']->balance_after,
                    'Purchase: JAMB Original Result'
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

        return $this->repo->pendingRequests();
    }

    public function take(string $id, User $admin)
    {
        if (! $admin->hasRole('administrator')) {
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

            // Notify user (job completed, awaiting approval)
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

    public function myJobs(User $admin)
    {
        if (! $admin->hasRole('administrator')) {
            abort(403, 'Unauthorized action');
        }

        return $this->repo->takenBy($admin->id);
    }

    /**
     * ======================
     * SUPER ADMIN
     * ======================
     */

    public function approve(string $id, User $superAdmin)
    {
        if (! $superAdmin->hasRole('superadmin')) {
            abort(403, 'Unauthorized financial action');
        }

        return DB::transaction(function () use ($id, $superAdmin) {
            $job = $this->repo->find($id);

            if ($job->status !== 'completed') {
                abort(422, 'Job is not ready for approval');
            }

            if ($job->is_paid) {
                abort(409, 'Job already paid');
            }

            // Debit superadmin and credit admin
            $this->walletService->adminCreditUser(
                $superAdmin,
                $job->completedBy,
                $job->admin_payout,
                'Payment for JAMB Result service (Request #' . $job->id . ')'
            );

            $job->update([
                'status'          => 'approved',
                'is_paid'         => true,
                'platform_profit' => $job->customer_price - $job->admin_payout,
                'approved_by'     => $superAdmin->id,
            ]);

            return [
                'message'         => 'Job approved and administrator paid from superadmin wallet',
                'job_id'          => $job->id,
                'admin_paid'      => $job->admin_payout,
                'platform_profit' => $job->platform_profit,
            ];
        });
    }

    public function reject(string $id, string $reason, User $superAdmin)
    {
        if (! $superAdmin->hasRole('superadmin')) {
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

            // Refund user from superadmin wallet
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

            // Notify user and admin
            Mail::to($job->email)->send(
                new JambResultServiceRejectionMail($job)
            );

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

    public function all()
    {
        if (! auth()->user()->hasRole('superadmin')) {
            abort(403, 'Unauthorized action');
        }

        return $this->repo->allWithRelations();
    }
}
