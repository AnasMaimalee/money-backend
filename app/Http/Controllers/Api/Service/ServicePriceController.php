<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ServicePriceService;
use App\Models\Service;

class ServicePriceController extends Controller
{
    public function __construct(
        protected ServicePriceService $servicePriceService
    ) {
    }

    public function list()
    {
        $admin = auth()->user();

        if (! $admin->hasRole('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $services = Service::where('active', true)
            ->get(['id', 'name', 'description', 'customer_price', 'admin_payout']);

        return response()->json($services);
    }

    /**
     * Update the prices and/or description of a service
     */
    public function update(Request $request, string $serviceId)
    {
        $admin = auth()->user();

        if (! $admin->hasRole('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'customer_price' => [
                'nullable',
                'numeric',
                'min:0',
                'required_without_all:admin_payout,description',
            ],
            'admin_payout' => [
                'nullable',
                'numeric',
                'min:0',
                'required_without_all:customer_price,description',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
                'min:10',
                'required_without_all:customer_price,admin_payout',
            ],
        ]);

        // No need to manually check payout > price anymore — repository handles it safely

        $service = $this->servicePriceService->updatePrices(
            $serviceId,
            $validated['customer_price'] ?? null,
            $validated['admin_payout'] ?? null,
            $validated['description'] ?? null,
        );

        return response()->json([
            'message' => 'Service updated successfully',
            'service' => [
                'id'             => $service->id,
                'name'           => $service->name,
                'description'    => $service->description,
                'customer_price' => $service->customer_price,
                'admin_payout'   => $service->admin_payout,
            ],
        ]);
    }
}
