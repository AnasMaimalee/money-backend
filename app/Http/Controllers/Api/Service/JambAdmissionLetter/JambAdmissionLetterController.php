<?php

namespace App\Http\Controllers\Api\Service\JambAdmissionLetter;

use App\Http\Controllers\Controller;
use App\Http\Resources\JambResultRequestResource;
use App\Models\JambAdmissionLetterRequest;
use App\Models\JambResultRequest;
use Illuminate\Http\Request;
use App\Services\JambAdmissionLetter\JambAdmissionLetterService;
use App\Http\Resources\JambAdmissionLetterRequestResource;

class JambAdmissionLetterController extends Controller
{
    public function __construct(
        protected JambAdmissionLetterService $service
    ) {}

    /**
     * ======================
     * USER
     * ======================
     */

    // User sees only his own requests
    public function my()
    {
        return response()->json(
            $this->service->my(auth()->user())
        );
    }

    public function processedByAdmin()
    {
        $admin = auth()->user();

        if (! $admin->hasRole('administrator')) {
            abort(403, 'Only administrators can view processed jobs');
        }

        $jobs = JambAdmissionLetterRequest::with([
            'user',
            'service',
            'completedBy.roles',
        ])
            ->where('completed_by', $admin->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'message' => 'Jobs processed by logged-in administrator',
            'data' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,

                    'user' => [
                        'name'  => $job->user->name,
                        'email' => $job->user->email,
                    ],
                    'payment' => [
                        'is_paid' => $job->is_paid,
                        'paid_at' => $job->paid_at,
                    ],
                    'service' => $job->service->name,

                    'completed_by' => [
                        'id'   => $job->completedBy->id,
                        'name' => $job->completedBy->name,
                        'role' => $job->completedBy->roles->pluck('name')->first(),
                    ],

                    'result_file_url' => $job->result_file
                        ? asset('storage/' . $job->result_file)
                        : null,

                    'processed_at' => $job->updated_at,
                ];
            }),
        ]);
    }

    // User submits request
    public function store(Request $request)
    {

        $data = $request->validate([
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string',
            'profile_code' => 'nullable|string',
            'registration_number' => 'nullable|string',
        ]);

        return response()->json(
            $this->service->submit(auth()->user(), $data),
            201
        );

    }

    public function myJobs()
    {
        return JambAdmissionLetterRequestResource::collection(
            JambAdmissionLetterRequest::where('status', 'processing')
                ->where('taken_by', auth()->id())
                ->latest()
                ->get()
        );
    }
    /**
     * ======================
     * ADMIN / SUPER ADMIN
     * ======================
     */

    // 🔥 index = admin overview (taken + completed)
    public function index()
    {
        return response()->json(
            $this->service->index(auth()->user())
        );
    }

    // Only unassigned jobs
    public function pending()
    {
        return JambAdmissionLetterRequestResource::collection(
            $this->service->pending()->sortByDesc('created_at')
        );
    }

    /**
     * ======================
     * ADMINISTRATOR (WORKER)
     * ======================
     */

    // Take (lock) a job
    public function take(string $id)
    {
        return response()->json(
            $this->service->take($id, auth()->user())
        );
    }

    // Complete job (upload admission letter)
    public function complete(Request $request, string $admissionRequest)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png',
        ]);

        $path = $request->file('file')
            ->store('jamb-admission-letters', 'public');

        return response()->json(
            $this->service->complete(
                $admissionRequest,
                $path,
                auth()->user()
            )
        );
    }

    /**
     * ======================
     * SUPER ADMIN
     * ======================
     */

    public function approve(string $id)
    {
        return response()->json(
            $this->service->approve($id, auth()->user())
        );
    }

    public function reject(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        return response()->json(
            $this->service->reject(
                $id,
                $request->reason,
                auth()->user()
            )
        );
    }

    // Super admin sees everything
    public function all()
    {
        return response()->json(
            $this->service->all()
        );
    }
}
