<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\WalletHistoryService;
use App\Services\WalletPdfService;
use App\Models\User;

class WalletHistoryController extends Controller
{
    public function __construct(
        protected WalletHistoryService $historyService,
        protected WalletPdfService $pdfService
    ) {}

    /**
     * Logged-in user (JSON)
     */
    public function myHistory(Request $request)
    {
        return response()->json([
            'data' => $this->historyService->getHistory(
                $request->all(),
                auth()->user()
            )
        ]);
    }

    /**
     * Logged-in user (PDF)
     */
    public function myHistoryPdf(Request $request)
    {
        $transactions = $this->historyService->getHistory(
            $request->all(),
            auth()->user()
        );

        return $this->pdfService
            ->generate($transactions, $request->all(), auth()->user())
            ->download('my-wallet-history.pdf');
    }

    /**
     * Admin / Superadmin – single user PDF
     */
    public function userHistoryPdf(Request $request, User $user)
    {
        $transactions = $this->historyService->getHistory(
            $request->all(),
            $user
        );

        return $this->pdfService
            ->generate($transactions, $request->all(), $user)
            ->download("wallet-{$user->id}.pdf");
    }

    /**
     * Superadmin – all users PDF
     */
    public function allHistoryPdf(Request $request)
    {
        $transactions = $this->historyService->getHistory($request->all());

        return $this->pdfService
            ->generate($transactions, $request->all())
            ->download('all-wallet-history.pdf');
    }
}
