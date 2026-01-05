<?php

namespace App\Services\JambAdmissionStatus;

use App\Mail\JambAdmissionStatusCompletedMail;
use App\Models\User;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Repositories\JambAdmissionStatus\JambAdmissionStatusRepository;
use App\Services\WalletService;
use App\Mail\WalletDebited;
use App\Mail\JambAdmissionStatusRejectedMail;

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
            ->whereRaw('LOWER(name) = ?', ['jamb admission letter'])
            ->firstOrFail();

        // 🔒 Balance check
        if ($user->wallet->balance < $service->customer_price) {
            abort(422, 'Insufficient wallet balance');
        }

        return DB::transaction(function () use ($user, $data, $service) {

            // 1️⃣ Debit user wallet
            $this->walletService->debit(
                $user,
                $service->customer_price,
                'JAMB Admission Letter request'
            );

            // 2️⃣ Create request
            $request = $this->repo->create([
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

            // 3️⃣ Notify user of debit
            Mail::to($request->email)->send(
                new WalletDebited(
                    $user,
                    $service->customer_price,
                    $user->wallet->balance
                )
            );

            return $request;
        });
    }

    /**
     * ======================
     * ADMIN
     * ======================
     */

    public function pending()
    {
        return $this->repo->pending();
    }

    public function take(string $id, User $admin)
    {
        $job = $this->repo->find($id);

        if ($job->status !== 'pending') {
            abort(422, 'Job already taken');
        }

        $job->update([
            'status'   => 'processing',
            'taken_by' => $admin->id,
        ]);

        return $job;
    }

    public function complete(string $id, string $filePath, User $admin)
    {


        return DB::transaction(function () use ($id, $filePath, $admin) {

            $job = $this->repo->find($id);

            // Ensure the job is assigned to this admin
            if ($job->taken_by !== $admin->id) {
                abort(403, 'You did not take this job');
            }

            // Only allow completing jobs that are in processing state
            if ($job->status !== 'processing') {
                abort(422, 'Invalid job state');
            }

            // Update the job as completed
            $job->update([
                'status'       => 'completed', // marks as completed by admin, awaiting approval
                'result_file'  => $filePath,
                'completed_by' => $admin->id,
            ]);

            // Load relations for response
            $job->load(['user', 'service', 'completedBy']);

            // Send email notification to user
            Mail::to($job->user->email)->send(
                new JambAdmissionStatusCompletedMail($job)
            );

            return [
                'message' => 'Job completed and awaiting approval',
                'job' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'user' => [
                        'name' => $job->user->name,
                        'email' => $job->user->email,
                    ],
                    'service' => $job->service->name,
                    'completed_by' => $job->completedBy->name, // Administrator
                    'result_file_url' => $job->result_file ? asset('storage/' . $job->result_file) : null,
                    'created_at' => $job->created_at,
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


        return DB::transaction(function () use ($id, $superAdmin) {

            $job = $this->repo->find($id);

            if ($job->status !== 'completed') {
                abort(422, 'Job is not awaiting approval');
            }

            if (!$job->completedBy) {
                abort(422, 'Completed by Administrator not found');
            }

            // Pay admin
            $this->walletService->credit(
                $job->completedBy, // Administrator
                $job->admin_payout,
                'JAMB Admission Letter service payment'
            );

            // Update job
            $job->update([
                'status'          => 'approved',
                'approved_by'     => $superAdmin->id,
                'platform_profit' => $job->customer_price - $job->admin_payout,
                'is_paid'         => true, // mark payout as done
            ]);

            $job->load(['user', 'service', 'completedBy', 'approvedBy']);

            return [
                'message' => 'Job approved and admin paid successfully',
                'job' => [
                    'id' => $job->id,
                    'status' => $job->status,
                    'user' => [
                        'name' => $job->user->name,
                        'email' => $job->user->email,
                    ],
                    'service' => $job->service->name,
                    'completed_by' => $job->completedBy->name, // Administrator
                    'approved_by' => $job->approvedBy->name,   // Super Admin
                    'admin_paid' => $job->admin_payout,
                    'platform_profit' => $job->platform_profit,
                    'result_file_url' => $job->result_file ? asset('storage/' . $job->result_file) : null,
                    'created_at' => $job->created_at,
                ],
            ];
        });
    }


    public function reject(string $id, string $reason, User $superAdmin)
    {
        return DB::transaction(function () use ($id, $reason, $superAdmin) {

            $job = $this->repo->find($id);

            $this->walletService->transfer(
                $superAdmin,
                $job->user,
                $job->customer_price,
                'Refund for rejected Admission Letter request'
            );

            $job->update([
                'status'           => 'rejected',
                'rejected_by'      => $superAdmin->id,
                'rejection_reason' => $reason,
            ]);

            Mail::to($job->email)->send(
                new JambAdmissionStatusRejectedMail($job)
            );

            return [
                'message' => 'Job rejected and user refunded',
                'job_id' => $job->id,
                'refund_amount' => $job->customer_price,
                'reason' => $reason,
            ];
        });
    }

    public function all()
    {
        return $this->repo->allWithRelations();
    }
}
