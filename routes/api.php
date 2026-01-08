<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Wallet\WalletController;
use App\Http\Controllers\Api\Wallet\WalletHistoryController;
use App\Http\Controllers\Api\Wallet\PaymentController;
use App\Http\Controllers\Api\Wallet\ServiceRequestController;
use App\Http\Controllers\Api\Service\JambResult\JambResultController;
use App\Http\Controllers\Api\Service\JambAdmissionLetter\JambAdmissionLetterController;
use App\Http\Controllers\Api\Service\JambUploadStatus\JambUploadStatusController;
use App\Http\Controllers\Api\Service\JambAdmissionStatus\JambAdmissionStatusController;
use App\Http\Controllers\Api\Service\JambAdmissionResultNotification\JambAdmissionResultNotificationController;
use App\Http\Controllers\Api\Dashboard\AdminDashboardController;
use App\Http\Controllers\Api\Dashboard\UserDashboardController;
use App\Http\Controllers\Api\Dashboard\SuperAdminDashboardController;
use App\Http\Controllers\Api\Payout\AdminPayoutController;
use App\Http\Controllers\Api\Payout\PayoutTestController;
use App\Http\Controllers\Api\Webhooks\PaystackWebhookController;
use App\Http\Controllers\Api\Service\ServicePriceController;
use App\Http\Controllers\Api\Service\SuperAdminServiceController;
use App\Http\Controllers\Api\Service\AdminServiceController;
use App\Http\Controllers\Api\Service\UserServiceController;
use App\Http\Controllers\Api\Payout\BankAccountController;
use App\Http\Controllers\Api\AdminManagementController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\Auth\LoginAuditController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;

/* |--------------------------------------------------------------------------
 | Public Auth Routes
 |-------------------------------------------------------------------------- */
Route::prefix('auth')->group(function () {
    Route::post('/register', [MeController::class, 'register']);
    Route::post('/login', [MeController::class, 'login']);
});

// Password Reset
Route::post('forgot-password', [PasswordResetController::class, 'forgot']);
Route::post('reset-password', [PasswordResetController::class, 'reset']);

Route::middleware('auth:api')->post('/update-password', [MeController::class, 'updatePassword']);
Route::middleware('auth:api')->post('logout', [MeController::class, 'logout']);

// Email Verification
Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify');

Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware('auth:api')
    ->name('verification.send');

// Superadmin: Login Audits
Route::middleware(['auth:api', 'role:superadmin'])->get('/login-audits', [LoginAuditController::class, 'index']);
// Single user audits ✅ NEW
Route::get('/login-audits/user/{userId}', [LoginAuditController::class, 'user'])
    ->middleware('auth:api');
// Superadmin: Admin & User Management
Route::middleware(['auth:api', 'role:superadmin'])->group(function () {
    Route::post('/superadmin/admins', [AdminManagementController::class, 'store']);
    Route::post('superadmin/users', [UserManagementController::class, 'store']);
    Route::get('superadmin/admins', [AdminManagementController::class, 'index']);
});

/* |--------------------------------------------------------------------------
 | Paystack Webhook (Public - No Auth)
 |-------------------------------------------------------------------------- */
Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])
    ->middleware('paystack.webhook');

/* |--------------------------------------------------------------------------
 | Authenticated Routes
 |-------------------------------------------------------------------------- */
Route::middleware('auth:api')->group(function () {

    /* |--------------------------------------------------------------------------
     | User Profile
     |-------------------------------------------------------------------------- */
    Route::get('/me', [MeController::class, 'me'])->middleware('role:administrator,superadmin,user');
    Route::post('/me/create-administrator', [MeController::class, 'createAdministrator']);

    /* |--------------------------------------------------------------------------
     | Administrator & User Management (Superadmin only)
     |-------------------------------------------------------------------------- */
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/administrator', [AdminManagementController::class, 'index']);
        Route::delete('/administrator/{adminId}', [AdminManagementController::class, 'destroy']);
        Route::post('/administrator/{id}/restore', [AdminManagementController::class, 'restore']);

        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/{userId}', [UserManagementController::class, 'show']);
            Route::post('/{userId}/fund', [UserManagementController::class, 'fundWallet']);
            Route::post('/{userId}/debit', [UserManagementController::class, 'debitWallet']);
            Route::get('/{userId}/transactions', [UserManagementController::class, 'transactions']);
            Route::delete('/{userId}', [UserManagementController::class, 'destroy']);
            Route::post('/{id}/restore', [UserManagementController::class, 'restore']);
        });
    });

    /* |--------------------------------------------------------------------------
     | Wallet
     |-------------------------------------------------------------------------- */
    Route::prefix('wallet')->middleware('auth:api')->group(function () {
        Route::get('/', [WalletController::class, 'index']);
        Route::get('/me', [WalletController::class, 'me']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::post('/initialize', [PaymentController::class, 'initialize']);
        Route::post('/verify', [PaymentController::class, 'verify']);
    });

    /* |--------------------------------------------------------------------------
     | Generic Service Request (if needed elsewhere)
     |-------------------------------------------------------------------------- */
    Route::post('/service/request', [ServiceRequestController::class, 'request']);

    /* |--------------------------------------------------------------------------
     | JAMB Services – Dynamic Routing (FIXED)
     |-------------------------------------------------------------------------- */
    $jambServices = [
        'jamb-result'                       => JambResultController::class,
        'jamb-admission-letter'             => JambAdmissionLetterController::class,
        'jamb-upload-status'                => JambUploadStatusController::class,
        'jamb-admission-status'             => JambAdmissionStatusController::class,
        'jamb-admission-result-notification'=> JambAdmissionResultNotificationController::class,
    ];

    foreach ($jambServices as $prefix => $controller) {
        Route::prefix("services/{$prefix}")->group(function () use ($controller) {
            // User routes
            Route::post('/', [$controller, 'store']);                    // Create request
            Route::get('/my', [$controller, 'my']);                       // User's own requests

            // Admin routes
            Route::middleware('role:administrator')->group(function () use ($controller) {
                Route::get('/pending', [$controller, 'pending']);
                Route::get('/administrator', [$controller, 'processedByAdmin']);
                Route::post('/{id}/take', [$controller, 'take']);
                Route::post('/{id}/complete', [$controller, 'complete']);
            });

            // Superadmin routes
            Route::middleware('role:superadmin')->group(function () use ($controller) {
                Route::post('/{id}/approve', [$controller, 'approve']);
                Route::post('/{id}/reject', [$controller, 'reject']);
                Route::get('/all', [$controller, 'all']);
            });
        });
    }

    /* |--------------------------------------------------------------------------
     | Dashboards
     |-------------------------------------------------------------------------- */
    Route::get('/dashboard/user', [UserDashboardController::class, 'index']);

    Route::middleware('role:administrator')->get('/dashboard/admin', [AdminDashboardController::class, 'index']);

    Route::middleware('role:superadmin')->get('/dashboard/superadmin', [SuperAdminDashboardController::class, 'index']);

    /* |--------------------------------------------------------------------------
     | Services Listing & Pricing
     |-------------------------------------------------------------------------- */
    Route::get('/services', [UserServiceController::class, 'index']);

    Route::middleware('role:administrator')->get('/admin/services', [AdminServiceController::class, 'index']);

    Route::middleware('role:superadmin')->group(function () {
        Route::get('/superadmin/services', [SuperAdminServiceController::class, 'index']);
        Route::get('/services/list', [ServicePriceController::class, 'list']);
        Route::put('/services/{serviceId}/update-prices', [ServicePriceController::class, 'update']);
    });

    /* |--------------------------------------------------------------------------
     | Payouts & Bank Accounts
     |-------------------------------------------------------------------------- */
    Route::middleware('role:administrator')->group(function () {
        Route::post('/admin/payout/request', [AdminPayoutController::class, 'requestPayout']);
        Route::post('/wallet/payout/request', [AdminPayoutController::class, 'requestPayout']);
    });

    Route::middleware('role:superadmin')->post('/superadmin/payout/{payout}/approve', [AdminPayoutController::class, 'approvePayout']);

    Route::prefix('admin/payout')->group(function () {
        Route::middleware('role:administrator')->group(function () {
            Route::get('bank', [BankAccountController::class, 'show']);
            Route::post('bank', [BankAccountController::class, 'storeOrUpdate']);
            Route::get('/my-request', [AdminPayoutController::class, 'myPayoutRequests']);
            Route::post('request', [AdminPayoutController::class, 'requestPayout']);
        });

        Route::middleware('role:superadmin')->group(function () {
            Route::get('/request', [AdminPayoutController::class, 'listRequests']);
            Route::post('/{payout}/approve', [AdminPayoutController::class, 'approvePayout']);
            Route::post('/{payout}/reject', [AdminPayoutController::class, 'rejectPayout']);

        });
    });

    /* |--------------------------------------------------------------------------
     | Wallet History & PDF
     |-------------------------------------------------------------------------- */
    Route::get('/wallet/history', [WalletHistoryController::class, 'myHistory']);
    Route::get('/wallet/history/pdf', [WalletHistoryController::class, 'myHistoryPdf']);

    Route::middleware('role:admin|superadmin')->get('/wallet/history/pdf/user/{user}', [WalletHistoryController::class, 'userHistoryPdf']);

    Route::middleware('role:superadmin')->get('/wallet/history/pdf/all', [WalletHistoryController::class, 'allHistoryPdf']);

    /* |--------------------------------------------------------------------------
     | Test Route (Development Only)
     |-------------------------------------------------------------------------- */
    Route::post('/test/payout/factory', [PayoutTestController::class, 'seed']);
});

/*
 * downlaoding files
 * */
// JAMB PDF DOWNLOAD - PERFECT
Route::get('/storage/{path}', function (string $path) {
    $filePath = storage_path('app/public/' . $path);

    if (!file_exists($filePath)) {
        abort(404, 'File not found');
    }

    return response()->file($filePath, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="jamb-result.pdf"',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->where('path', '.*');
