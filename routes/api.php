<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Wallet\WalletController;
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
/*
|--------------------------------------------------------------------------
| Public Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [MeController::class, 'register']);
    Route::post('/login', [MeController::class, 'login']);
});


Route::middleware(['auth:api', 'role:superadmin'])->group(function () {
    Route::get('/login-audits', [LoginAuditController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Paystack Webhook (Public - No Auth)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])
    ->middleware('paystack.webhook');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | User Profile
    |--------------------------------------------------------------------------
    */
    Route::get('/me', [MeController::class, 'me']);
    Route::post('/me/create-administrator', [MeController::class, 'createAdministrator']);

/*
 * Administrator Management Controller*
 */
    Route::middleware(['auth:api'])->middleware('role:superadmin')->group(function () {
        Route::get('/administrator', [AdminManagementController::class, 'index']);
        Route::delete('/administrator/{adminId}', [AdminManagementController::class, 'destroy']);
        Route::post('/administrator/{id}/restore', [AdminManagementController::class, 'restore']);
        Route::post('users/{userId}/debit', [UserManagementController::class, 'debitWallet']);
        Route::get('users/{userId}/transactions', [UserManagementController::class, 'transactions']);
    });

    /*
     * User Management Controller
    */


    Route::middleware(['auth:api'])->prefix('users')->middleware('role:superadmin')->group(function () {
        Route::get('/', [UserManagementController::class, 'index']);                    // List + search
        Route::get('/{userId}', [UserManagementController::class, 'show']);             // View user
        Route::post('/{userId}/fund', [UserManagementController::class, 'fundWallet']);
        Route::post('/{userId}/debit', [UserManagementController::class, 'debitWallet']);
        Route::get('/{userId}/transactions', [UserManagementController::class, 'transactions']);// Manual fund
        Route::delete('/{userId}', [UserManagementController::class, 'destroy']);       // Soft delete
        Route::post('/{id}/restore', [UserManagementController::class, 'restore']);     // Restore
    });
    /*
    |--------------------------------------------------------------------------
    | Wallet
    |--------------------------------------------------------------------------
    */
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class, 'index']);
        Route::get('/me', [WalletController::class, 'me']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::post('/initialize', [PaymentController::class, 'initialize']);
        Route::post('/verify', [PaymentController::class, 'verify']);
    });

    /*
    |--------------------------------------------------------------------------
    | Generic Service Request
    |--------------------------------------------------------------------------
    */
    Route::post('/service/request', [ServiceRequestController::class, 'request']);

    /*
    |--------------------------------------------------------------------------
    | JAMB Services (Repeating Pattern)
    |--------------------------------------------------------------------------
    */
    $jambServices = [
        'jamb-result' => JambResultController::class,
        'jamb-admission-letter' => JambAdmissionLetterController::class,
        'jamb-upload-status' => JambUploadStatusController::class,
        'jamb-admission-status' => JambAdmissionStatusController::class,
        'jamb-admission-result-notification' => JambAdmissionResultNotificationController::class,
    ];

    foreach ($jambServices as $prefix => $controller) {
        Route::prefix("services/{$prefix}")->group(function () use ($controller) {

            // ================= USER =================
            Route::post('/', [JambResultController::class, 'store']); // Only jamb-result has role:user, others don't — adjust if needed
            Route::get('/my', [$controller, 'my']);
            Route::get('/administrator', [$controller, 'processedByAdmin'])
                ->middleware('role:administrator');

            // ================= ADMIN =================
            Route::get('/pending', [$controller, 'pending'])
                ->middleware('role:administrator');

            Route::post('/{id}/take', [$controller, 'take'])
                ->middleware('role:administrator');

            Route::post('/{id}/complete', [$controller, 'complete'])
                ->middleware('role:administrator');

            // ================= SUPER ADMIN =================
            Route::post('/{id}/approve', [$controller, 'approve'])
                ->middleware('role:superadmin');

            Route::post('/{id}/reject', [$controller, 'reject'])
                ->middleware('role:superadmin');

            Route::get('/all', [$controller, 'all'])
                ->middleware('role:superadmin');
        });
    }

    // Special case: Only jamb-result has these extra user/admin routes with explicit roles
    Route::prefix('services/jamb-result')->group(function () {
        Route::get('/my', [JambResultController::class, 'my'])->middleware('role:user');
        Route::post('/', [JambResultController::class, 'store'])->middleware('role:user');
        Route::get('/administrator', [JambResultController::class, 'processedByAdmin'])
            ->middleware('role:administrator');
    });

    /*
    |--------------------------------------------------------------------------
    | Dashboards
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard/user', [UserDashboardController::class, 'index']);

    Route::middleware('role:administrator')->group(function () {
        Route::get('/dashboard/admin', [AdminDashboardController::class, 'index']);
    });

    Route::middleware('role:superadmin')->group(function () {
        Route::get('/dashboard/superadmin', [SuperAdminDashboardController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | Services Listing & Pricing (Role-based)
    |--------------------------------------------------------------------------
    */
    Route::get('/services', [UserServiceController::class, 'index']);

    Route::middleware('role:administrator')->get('/admin/services', [AdminServiceController::class, 'index']);

    Route::middleware('role:superadmin')->group(function () {
        Route::get('/superadmin/services', [SuperAdminServiceController::class, 'index']);
        Route::get('/services/list', [ServicePriceController::class, 'list']);
        Route::put('/services/{serviceId}/update-prices', [ServicePriceController::class, 'update']);
    });

    /*
    |--------------------------------------------------------------------------
    | Payouts & Bank Accounts
    |--------------------------------------------------------------------------
    */
    // Admin payout request
    Route::middleware('role:administrator')->group(function () {
        Route::post('/admin/payout/request', [AdminPayoutController::class, 'requestPayout']);
        Route::post('/wallet/payout/request', [AdminPayoutController::class, 'requestPayout']);
    });

    // Superadmin payout approval
    Route::middleware('role:superadmin')->post('/superadmin/payout/{payout}/approve', [AdminPayoutController::class, 'approvePayout']);

    // Bank account management + payout (using sanctum? – kept as-is but grouped cleanly)
    Route::middleware('auth:api')->prefix('admin/payout')->group(function () {
        Route::get('bank', [BankAccountController::class, 'show'])
            ->middleware('role:administrator');
        Route::post('bank', [BankAccountController::class, 'storeOrUpdate'])
            ->middleware('role:administrator');
        Route::get('/request', [AdminPayoutController::class, 'listRequests'])
            ->middleware('role:superadmin');
        Route::get('/myrequest', [AdminPayoutController::class, 'myPayoutRequests'])
            ->middleware('role:administrator');
        Route::post('request', [AdminPayoutController::class, 'requestPayout'])
            ->middleware('role:administrator');
        Route::post('approve/{payout}', [AdminPayoutController::class, 'approvePayout'])
            ->middleware('role:superadmin');
    });

    /*
    |--------------------------------------------------------------------------
    | Test Route (Development Only)
    |--------------------------------------------------------------------------
    */
    Route::post('/test/payout/factory', [PayoutTestController::class, 'seed']);
});
