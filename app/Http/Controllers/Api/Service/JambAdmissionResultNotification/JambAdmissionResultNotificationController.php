<?php

namespace App\Http\Controllers\Api\Service\JambAdmissionResultNotification;

use App\Http\Controllers\Controller;
use App\Http\Resources\JambAdmissionLetterRequestResource;
use App\Http\Resources\JambAdmissionResultNotificationRequestResource;
use App\Models\JambAdmissionResultNotificationRequest;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Services\JambAdmissionResultNotification\JambAdmissionResultNotificationService;

class JambAdmissionResultNotificationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected JambAdmissionResultNotificationService $service
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

        $jobs = JambAdmissionResultNotificationRequest::with([
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
            'email' => 'required|email',
            'phone_number' => 'required|string',
            'profile_code' => 'required|string',
            'registration_number' => 'nullable|string',
        ]);

        return response()->json(
            $this->service->submit(auth()->user(), $data),
            201
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
        return JambAdmissionResultNotificationRequestResource::collection(
            $this->service->pending()->sortByDesc('created_at')
        );
    }
    public function myJobs()
    {
        return JambAdmissionResultNotificationRequestResource::collection(
            JambAdmissionResultNotificationRequest::where('status', 'processing')
                ->where('taken_by', auth()->id())
                ->latest()
                ->get()
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

    public function download(string $id)
    {
        $job = JambAdmissionResultNotificationRequest::findOrFail($id);
        $this->authorize('download', $job);

        abort_if(
            ! $job->result_file || ! Storage::disk('public')->exists($job->result_file),
            404,
            'File not available'
        );

        // 📂 Full file path
        $path = $job->result_file;

        // 📎 Extension (pdf, png, jpg, jpeg)
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // 🧠 Detect mime type properly
        $mime = Storage::disk('public')->mimeType($path);

        // 🏷️ Clean filename
        $filename = "JAMB_Admission_Result_Notification_{$job->id}.{$extension}";

        return response()->download(
            Storage::disk('public')->path($path),
            $filename,
            [
                'Content-Type' => $mime,
            ]
        );


    }
}
