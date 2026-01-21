<?php

namespace App\Http\Controllers\Api\Service\JambAdmissionLetter;

use App\Http\Controllers\Controller;
use App\Http\Resources\JambResultRequestResource;
use App\Models\JambAdmissionLetterRequest;
use App\Models\JambResultRequest;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Storage;
use App\Services\JambAdmissionLetter\JambAdmissionLetterService;
use App\Http\Resources\JambAdmissionLetterRequestResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;

class JambAdmissionLetterController extends Controller
{
  use AuthorizesRequests;

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

        abort_if(! $admin->hasRole('administrator'), 403);

        $jobs = JambAdmissionLetterRequest::with([
            'user',
            'service',
            'completedBy',
        ])
            ->where('completed_by', $admin->id)
            ->latest()
            ->get();

        return JambAdmissionLetterRequestResource::collection($jobs);
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

        $job = $this->service->complete($admissionRequest, $path, auth()->user());

        return response()->json([
            'message' => 'Job completed and awaiting superadmin approval',
            'data' => [
                'id' => $job->id,
                'status' => $job->status,
                'result_file' => $job->result_file,
                'result_file_url' => route('services.jamb-admission-letter.download', $job->id),
                'user' => [
                    'name' => $job->user->name,
                    'email' => $job->user->email,
                ],
                'service' => $job->service->name,
                'completed_by' => $job->completedBy->name,
                'created_at' => $job->created_at->toDateTimeString(),
            ]
        ]);
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
        $job = JambAdmissionLetterRequest::findOrFail($id);
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
        $filename = "JAMB_Admission_Letter_{$job->id}.{$extension}";

        return response()->download(
            Storage::disk('public')->path($path),
            $filename,
            [
                'Content-Type' => $mime,
            ]
        );


    }
}