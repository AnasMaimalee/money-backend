<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;

class WalletPdfService
{
    public function generate(
        $transactions,
        array $filters = [],
        ?User $user = null
    ) {
        return Pdf::loadView('pdf.wallet-history', [
            'transactions' => $transactions,
            'filters'      => $filters,
            'user'         => $user,
            'generatedAt'  => now(),
            'company'      => config('app.name'),
        ]);
    }
}
