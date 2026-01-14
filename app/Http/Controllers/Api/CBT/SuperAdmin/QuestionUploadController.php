<?php

namespace App\Http\Controllers\Api\CBT\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\CBT\SuperAdmin\QuestionUploadService;
use Illuminate\Http\Request;

class QuestionUploadController extends Controller
{
    public function __construct(
        protected QuestionUploadService $uploadService
    ) {}

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        return response()->json(
            $this->uploadService->upload($request->file('file'))
        );
    }
}
