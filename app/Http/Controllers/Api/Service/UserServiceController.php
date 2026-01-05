<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Service;

class UserServiceController extends Controller
{
    /**
     * List active services and prices user will be charged
     */
    public function index()
    {
        $services = Service::query()
            ->where('active', true)
            ->select([
                'id',
                'name',
                'customer_price',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($service) => [
                'id'    => $service->id,
                'name'  => $service->name,
                'price' => (float) $service->customer_price,
            ]);

        return response()->json([
            'data' => $services,
        ]);
    }
}
