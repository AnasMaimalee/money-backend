<?php

namespace App\Repositories;

use App\Models\Service;

class ServiceRepository
{
    public function find(string $id): Service
    {
        return Service::findOrFail($id);
    }

    public function updatePrices(
        Service $service,
        ?float $customerPrice = null,
        ?float $adminPayout = null
    ): Service {
        $data = [];

        if (! is_null($customerPrice)) {
            $data['customer_price'] = $customerPrice;
        }

        if (! is_null($adminPayout)) {
            $data['admin_payout'] = $adminPayout;
        }

        if (empty($data)) {
            abort(422, 'Nothing to update');
        }

        // Safety check
        if (
            isset($data['customer_price'], $data['admin_payout']) &&
            $data['admin_payout'] > $data['customer_price']
        ) {
            abort(422, 'Admin payout cannot be greater than customer price');
        }

        $service->update($data);

        return $service->fresh();
    }
}
