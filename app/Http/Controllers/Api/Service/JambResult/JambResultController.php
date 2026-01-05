<?php

namespace App\Http\Controllers\Api\Service\JambResult;

use App\Http\Controllers\Controller;
use App\Models\JambResultRequest;
use Illuminate\Http\Request;
use App\Services\JambResult\JambResultService;
use App\Http\Resources\JambResultRequestResource;
class JambResultController extends Controller
{
    public function __construct(
        protected JambResultService $service
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

        $jobs = JambResultRequest::with([
            'user',
            'service',
            'completedBy.roles', // ✅ FIXED
        ])
            ->where('completed_by', $admin->id)
            ->where('status', 'completed')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Jobs processed by administrator',
            'data' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,

                    'user' => [
                        'name'  => $job->user->name,
                        'email' => $job->user->email,
                    ],

                    'service' => $job->service->name,

                    // ✅ Completed admin details + role
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
        $request->validate([
            'email' => 'required|email',
            'phone_number' => 'nullable|string',
            'registration_number' => 'nullable|string',
        ]);

        return response()->json(
            $this->service->submit(auth()->user(), $request->all())
        );
    }

    /**
     * ======================
     * ADMIN / SUPER ADMIN
     * ======================
     */

// 🔥 index = admin overview
    public function index()
    {
        return response()->json(
            $this->service->index(auth()->user())
        );
    }

// Only unassigned jobs
    public function pending()
    {
        return JambResultRequestResource::collection(
            $this->service->pending()
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

// Complete job
    public function complete(Request $request, string $jambRequest)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png',

        ]);

        $path = $request->file('file')->store('jamb-results', 'public');

        return response()->json(
            $this->service->complete(
                $jambRequest,
                $path,
                auth()->user()
            )
        );
    }

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

    public function all()
    {
        return response()->json(
            $this->service->all()
        );
    }
}
