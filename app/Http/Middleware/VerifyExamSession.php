<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CBT\ExamSessionService;

class VerifyExamSession
{
    protected ExamSessionService $sessionService;

    public function __construct(ExamSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $examId = $request->route('exam');

        if (!$examId) {
            return response()->json(['message' => 'Exam ID is required'], 400);
        }

        $session = $this->sessionService->getSession($examId);

        if (!$session) {
            return response()->json(['message' => 'Exam session not found'], 404);
        }

        if ($session->status !== 'ongoing') {
            return response()->json(['message' => 'Exam session has ended or submitted'], 403);
        }

        if (now()->greaterThan($session->expires_at)) {
            return response()->json(['message' => 'Exam session has expired'], 403);
        }

        return $next($request);
    }
}
