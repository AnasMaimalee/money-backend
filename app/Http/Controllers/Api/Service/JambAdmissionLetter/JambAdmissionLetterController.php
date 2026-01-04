<?php

namespace App\Http\Controllers\Api\Service\JambAdmissionLetter;

use App\Http\Controllers\Controller;
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
        return JambAdmissionLetterRequestResource::collection(
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
