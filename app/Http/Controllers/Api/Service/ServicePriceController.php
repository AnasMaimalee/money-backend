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
            ->get(['id', 'name', 'customer_price', 'admin_payout']);

        return response()->json($services);
    }

    /**
     * Update the prices of a service
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
                'required_without:admin_payout',
            ],
            'admin_payout' => [
                'nullable',
                'numeric',
                'min:0',
                'required_without:customer_price',
            ],
        ]);

        if (
            isset($validated['customer_price'], $validated['admin_payout']) &&
            $validated['admin_payout'] > $validated['customer_price']
        ) {
            abort(422, 'Admin payout cannot be greater than customer price');
        }

        $service = $this->servicePriceService->updatePrices(
            $serviceId,
            $validated['customer_price'] ?? null,
            $validated['admin_payout'] ?? null
        );

        return response()->json([
            'message' => 'Service prices updated successfully',
            'service' => [
                'id'             => $service->id,
                'name'           => $service->name,
                'customer_price' => $service->customer_price,
                'admin_payout'   => $service->admin_payout,
            ],
        ]);
    }

}
