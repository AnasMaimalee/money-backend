<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Service;

class AdminServiceController extends Controller
{
    /**
     * List all services with full pricing info
     */
    public function index()
    {
        $services = Service::query()
            ->select([
                'id',
                'name',
                'admin_payout',
                'active',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($service) => [
                'id'              => $service->id,
                'name'            => $service->name,
                'admin_payout'    => (float) $service->admin_payout,
                'active' => (bool) $service->active,
            ]);

        return response()->json([
            'data' => $services,
        ]);
    }
}
