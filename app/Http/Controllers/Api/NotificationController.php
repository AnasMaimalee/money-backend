<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()
                ->notifications()
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => $n->data['type'],
                    'message' => $n->data['message'],
                    'exam_id' => $n->data['exam_id'] ?? null,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ])
        );
    }
}
