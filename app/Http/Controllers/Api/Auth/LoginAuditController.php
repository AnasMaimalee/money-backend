<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAudit;
use Illuminate\Http\Request;

class LoginAuditController extends Controller
{
    /**
     * Show login audits (superadmin only)
     */
    public function index(Request $request)
    {
        $query = LoginAudit::with('user')->latest();

        // Optional filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('success')) {
            $query->where('success', (bool) $request->success);
        }

        $audits = $query->paginate(20);

        return response()->json([
            'message' => 'Login audits retrieved successfully',
            'data' => $audits,
        ]);
    }
}
