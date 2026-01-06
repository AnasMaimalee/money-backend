<?php

namespace App\Repositories;

use App\Models\Service;

class ServiceRepository
{
    public function find(string $id): Service
    {
        return Service::findOrFail($id);
    }

    public function update(
        Service $service,
        ?float $customerPrice = null,
        ?float $adminPayout = null,
        ?string $description = null
    ): Service {
        $data = [];

        if (!is_null($customerPrice)) {
            $data['customer_price'] = $customerPrice;
        }

        if (!is_null($adminPayout)) {
            $data['admin_payout'] = $adminPayout;
        }

        if (!is_null($description)) {
            $data['description'] = $description;
        }

        if (empty($data)) {
            abort(422, 'Nothing to update');
        }

        // Get the values that will be used after update
        $newCustomerPrice = $data['customer_price'] ?? $service->customer_price;
        $newAdminPayout   = $data['admin_payout'] ?? $service->admin_payout;

        // Enforce: admin_payout must never be greater than customer_price
        if ($newAdminPayout > $newCustomerPrice) {
            abort(422, 'Admin payout cannot be greater than customer price');
        }

        $service->update($data);

        return $service->fresh();
    }
}
