<?php

namespace App\Http\Controllers\Api\CBT;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CBT\SubjectService;
use App\Models\Subject;

class SubjectController extends Controller
{
    public function __construct(protected SubjectService $service) {}

    // List all subjects (admin + user)
    public function index()
    {
        $subjects = $this->service->listSubjects();
        return response()->json([
            'status' => 'success',
            'data' => $subjects
        ]);
    }

    // View single subject (optional)
    public function show(string $id)
    {
        $subject = $this->service->getSubject($id);
        return response()->json([
            'status' => 'success',
            'data' => $subject
        ]);
    }

    // Create subject (admin only)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:subjects,name',
            'slug' => 'required|string|unique:subjects,slug',
            'status' => 'sometimes|boolean'
        ]);

        $subject = $this->service->createSubject($request->only(['name', 'slug', 'status']));

        return response()->json([
            'status' => 'success',
            'message' => 'Subject created successfully',
            'data' => $subject
        ], 201);
    }

    // Update subject (admin only)
    public function update(Request $request, Subject $subject)
    {
        $request->validate([
            'name' => 'sometimes|required|string|unique:subjects,name,' . $subject->id,
            'slug' => 'sometimes|required|string|unique:subjects,slug,' . $subject->id,
            'status' => 'sometimes|boolean'
        ]);

        $subject = $this->service->updateSubject($subject, $request->only(['name', 'slug', 'status']));

        return response()->json([
            'status' => 'success',
            'message' => 'Subject updated successfully',
            'data' => $subject
        ]);
    }
}
