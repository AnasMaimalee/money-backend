<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Service;

class SuperAdminServiceController extends Controller
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
                'description',
                'customer_price',
                'admin_payout',
                'active',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($service) => [
                'id'              => $service->id,
                'name'            => $service->name,
                'description'      => $service->description,
                'customer_price'  => (float) $service->customer_price,
                'admin_payout'    => (float) $service->admin_payout,
                'platform_profit' => (float) (
                    $service->customer_price - $service->admin_payout
                ),
                'active' => (bool) $service->active,
            ]);

        return response()->json([
            'data' => $services,
        ]);
    }
}
