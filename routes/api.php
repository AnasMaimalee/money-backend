<?php

use App\Http\Controllers\Api\CBT\ExamResultController;
use App\Http\Controllers\Api\WebauthnController;
use App\Http\Controllers\Api\Service\JambPinBinding\JambPinBindingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
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
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Profile\ProfileController;
use App\Http\Controllers\Api\Feedback\FeedbackController;
/*
 * CBT Controller
 * */

use App\Http\Controllers\Api\CBT\ExamController;
use App\Http\Controllers\Api\CBT\SuperAdmin\ExamManagementController;
use App\Http\Controllers\Api\CBT\ResultController;
use App\Http\Controllers\Api\CBT\SuperAdmin\QuestionUploadController;
use App\Http\Controllers\Api\CBT\SuperAdmin\QuestionBankController;
use App\Http\Controllers\Api\CBT\WalletPaymentController;
use App\Http\Controllers\Api\CBT\ExamTimerController;
use App\Http\Controllers\Api\CBT\NotificationController;
use App\Http\Controllers\Api\CBT\SubjectController;
use App\Http\Controllers\Api\CBT\LeaderboardController;
use App\Http\Controllers\Api\CBT\SuperAdmin\AdminCbtController;
use App\Http\Controllers\Api\CBT\SuperAdmin\CbtSettingController;
use App\Http\Controllers\Api\CBT\SuperAdmin\LiveCbtController;




use Illuminate\Support\Facades\Broadcast;
Broadcast::routes();
require __DIR__.'/channels.php';


/* |--------------------------------------------------------------------------
 | Public Auth Routes
 |-------------------------------------------------------------------------- */
Route::prefix('auth')->group(function () {
    Route::post('/register', [MeController::class, 'register']);
    Route::post('/login', [MeController::class, 'login']);
    Route::post('/set-password', [PasswordController::class, 'resetPassword']);
    // routes/api.php
    Route::post('/login/check', [MeController::class, 'loginCheck']);

        // ->middleware('throttle:5,1');

        Route::middleware('auth:api')->group(function () {
        Route::post('/2fa/setup', [TwoFactorController::class, 'setup']);
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::get('/2fa/status', [TwoFactorController::class, 'twoFaStatus']);
    });
});


//finger print


/* ───────────── Authenticated WebAuthn ───────────── */
Route::middleware('auth:api')->group(function () {
    Route::post('/webauthn/register/options', [WebauthnController::class, 'registerOptions']);
    Route::post('/webauthn/register', [WebauthnController::class, 'register']);
    Route::get('/webauthn/credentials', [WebauthnController::class, 'index']);
    Route::delete('/webauthn/credentials', [WebauthnController::class, 'destroy']);
    // routes/api.php



});

Route::post('/webauthn/login/options', [WebauthnController::class, 'loginOptions']);
Route::post('/webauthn/login', [WebauthnController::class, 'login']);


// Password Reset
Route::post('forgot-password', [PasswordResetController::class, 'forgot']);
Route::post('reset-password', [PasswordResetController::class, 'reset']);
Route::middleware('auth:api')->get('/me', [MeController::class, 'me']);
Route::middleware('auth:api')->post('/update-password', [MeController::class, 'updatePassword']);
Route::middleware('auth:api')->post('logout', [MeController::class, 'logout']);

Route::middleware('auth:api')->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::post('/password', [ProfileController::class, 'updatePassword']);
    Route::middleware(['auth:sanctum'])->group(function () {
    
});

});

Route::middleware('auth:api')->prefix('/administrator')->group(function () {
    Route::post('/payout/bank', [ProfileController::class, 'updateBank']); // create
    Route::put('/payout/bank', [ProfileController::class, 'updateBank']);  // update
});



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
    ->middleware('throttle:60,1'); // 60/min generic

/* |--------------------------------------------------------------------------
 | Authenticated Routes
 |-------------------------------------------------------------------------- */
Route::middleware('auth:api')->group(function () {

    /* |--------------------------------------------------------------------------
     | User Profile
     |-------------------------------------------------------------------------- */
    Route::get('/me', [MeController::class, 'me'])->middleware('role:administrator,superadmin,user');
    Route::post('/me/create-administrator', [MeController::class, 'createAdministrator']);
    Route::get('/banks', [AdminManagementController::class, 'banks']);

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
            Route::get('/users/trashed', [UserManagementController::class, 'trashed']);
        });
    });

    /* |--------------------------------------------------------------------------
     | Wallet
     |-------------------------------------------------------------------------- */
    Route::prefix('wallet')->group(function () {
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
        'jamb-result'                        => JambResultController::class,
        'jamb-admission-letter'              => JambAdmissionLetterController::class,
        'jamb-upload-status'                 => JambUploadStatusController::class,
        'jamb-admission-status'              => JambAdmissionStatusController::class,
        'jamb-admission-result-notification' => JambAdmissionResultNotificationController::class,
        'jamb-pin-binding'                   => JambPinBindingController::class,
    ];

    Route::middleware('auth:api')->group(function () use ($jambServices) {

        foreach ($jambServices as $prefix => $controller) {

            Route::prefix("services/{$prefix}")->group(function () use ($controller, $prefix) {

                // ✅ DOWNLOAD (AUTH + POLICY)
                Route::get('/{id}/download', [$controller, 'download'])
                    ->name("services.{$prefix}.download");

                // ================= USER =================
                Route::post('/', [$controller, 'store']);
                Route::get('/my', [$controller, 'my']);

                // ================= ADMIN =================
                Route::middleware('role:administrator')->group(function () use ($controller) {
                    Route::get('/pending', [$controller, 'pending']);
                    Route::get('/my-pending-job', [$controller, 'myJobs']);
                    Route::get('/administrator', [$controller, 'processedByAdmin']);
                    Route::post('/{id}/take', [$controller, 'take']);
                    Route::post('/{id}/complete', [$controller, 'complete']);
                });

                // ================= SUPERADMIN =================
                Route::middleware('role:superadmin')->group(function () use ($controller) {
                    Route::post('/{id}/approve', [$controller, 'approve']);
                    Route::post('/{id}/reject', [$controller, 'reject']);
                    Route::get('/all', [$controller, 'all']);
                });
            });
        }
    });


    /* |--------------------------------------------------------------------------
     | Dashboards
     |-------------------------------------------------------------------------- */
    Route::middleware('role:user')->get('/dashboard/user', [UserDashboardController::class, 'index']);

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

    Route::middleware('role:administrator,superadmin')->get('/wallet/history/pdf/user/{user}', [WalletHistoryController::class, 'userHistoryPdf']);

    Route::middleware('role:superadmin')->get('/wallet/history/pdf/all', [WalletHistoryController::class, 'allHistoryPdf']);

    /* |--------------------------------------------------------------------------
     | Test Route (Development Only)
     |-------------------------------------------------------------------------- */
    Route::post('/test/payout/factory', [PayoutTestController::class, 'seed']);
});

Route::get('/landing-services', [ServicePriceController::class, 'landingPageServices']);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

Route::get('/download-storage/{path}', function (string $path) {
    try {
        // ✅ Use Laravel Storage Facade
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'File not found');
        }

        $file = Storage::disk('public')->path($path);
        $mimeType = Storage::disk('public')->mimeType($path);
        $originalName = basename($path);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return response()->file($file, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "attachment; filename=\"{$originalName}\"",
            'Content-Length' => Storage::disk('public')->size($path),
            // ✅ CORS HEADERS
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'Content-Disposition, Content-Type, Content-Length',
        ]);
    } catch (\Exception $e) {
        abort(404, 'Download failed');
    }
})->where('path', '.*');
/*
|--------------------------------------------------------------------------
| CBT Routes (Clean & Unified)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->prefix('cbt')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PUBLIC / SHARED
    |--------------------------------------------------------------------------
    */
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/subjects/{subject}', [SubjectController::class, 'show']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);

    /*
    |--------------------------------------------------------------------------
    | SUPERADMIN (MANAGEMENT)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
        /*
         * Exam Management Super Admin
         * */

        Route::get('/exams', [ExamManagementController::class, 'index']);
        Route::get('/live', [AdminCbtController::class, 'live']);
        Route::get('/exams/{exam}', [ExamController::class, 'show']);
        Route::get('/exams/{exam}/answers', [ExamManagementController::class, 'answers']);
        Route::get('/exams/{exam}/score', [ExamManagementController::class, 'score']);
        Route::get('/exams/{exam}/analytics', [ExamManagementController::class, 'analytics']);
        Route::get('/rankings', [ExamManagementController::class, 'rankings']);

        Route::post('/exams/{exam}/invalidate', [ExamManagementController::class, 'invalidate']);
        Route::post('/exams/{exam}/remark', [ExamManagementController::class, 'remark']);
        Route::get('/rankings/subject/{subjectId}', [ExamManagementController::class, 'rankingsBySubject']);
        Route::get('/exams/{exam}/pdf', [ResultController::class, 'downloadResult']);

        /*
         * Live CBT
         * */

        // Subjects
        Route::post('/subjects', [SubjectController::class, 'store']);
        Route::put('/subjects/{subject}', [SubjectController::class, 'update']);

        // Questions
        Route::get('/questions', [QuestionBankController::class, 'index']);
        Route::post('/questions/upload', [QuestionUploadController::class, 'upload']);
        Route::get('/questions/{question}/preview', [QuestionBankController::class, 'preview']);

        // Live CBT monitoring
        Route::get('/live', [AdminCbtController::class, 'live']);

        // Leaderboard (global)
        Route::get('/leaderboard', [LeaderboardController::class, 'index']);

        Route::get('/settings', [CbtSettingController::class, 'index']);
        Route::put('/settings', [CbtSettingController::class, 'update']);



    });

    /*
    |--------------------------------------------------------------------------
    | USER CBT EXAM FLOW
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:user')->prefix('user')->group(function () {

        // ---------------- EXAM LIFECYCLE ----------------
        Route::get('/exam-fee', [ExamController::class, 'examFee']);
        Route::post('/exam/start', [ExamController::class, 'start']);
        Route::get('/exam/ongoing', [ExamController::class, 'ongoingExams']);
        Route::get('/exam/{exam}', [ExamController::class, 'show']);
        Route::get('/exam/{exam}/meta', [ExamController::class, 'meta']);
        Route::get('/exam/resume', [ExamController::class, 'resume']);
        Route::get('/exam/time', [ExamController::class, 'time']);
        Route::post('/exam/{exam}/submit', [ExamController::class, 'submit']);
        Route::post('/exam/{exam}/auto-submit', [ExamController::class, 'autoSubmitExam']);

        // ---------------- ANSWERS ----------------
        Route::post('/exam/{exam}/answer/{answer}', [ExamController::class, 'submitAnswer']);

        // ---------------- RESULTS ----------------
        Route::get('/result-history', [ExamResultController::class, 'index']);
        Route::get('/results/{exam}', [ResultController::class, 'show']);
        Route::get('/results/{exam}/summary', [ResultController::class, 'summary']);
        Route::get('/results/{exam}/pdf', [ResultController::class, 'downloadResult']);
        Route::get('/results/{exam}/show-result', [ResultController::class, 'showResult']);
        Route::post('/exam/{exam}/refund', [ExamController::class, 'refundExamFee']);

        // ---------------- USER LEADERBOARD ----------------
        Route::get('/leaderboard', [LeaderboardController::class, 'selfRank']);

        // ---------------- WALLET ----------------
        Route::get('/exam/{exam}/wallet-check', [WalletPaymentController::class, 'check']);
        Route::post('/exam/{exam}/pay-wallet', [WalletPaymentController::class, 'payAndStart'])
            ->middleware('throttle:wallet');

        // ---------------- REFUND ----------------

    });

    /*
    |--------------------------------------------------------------------------
    | TIMER / HEARTBEAT (SECURED)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:user', 'verify.exam.session', 'cbt.last.seen'])->group(function () {
        Route::post('/exam/{exam}/heartbeat', [ExamTimerController::class, 'heartbeat']);
        Route::post('/exam/{exam}/check-time', [ExamTimerController::class, 'checkAndSubmit']);
    });
});

/*
 * Feedback Route
 * */

Route::middleware('auth:api')->group(function () {
    Route::middleware('role:superadmin')->prefix('/superadmin')->group(function () {
        Route::get('/feedback', [FeedbackController::class, 'index']);
        Route::patch('/feedback/{feedback}/status', [FeedbackController::class, 'updateStatus']);
    });
});

Route::post('/feedback', [FeedbackController::class, 'store']);
Route::get('/feedback', [FeedbackController::class, 'showUserFeedback']);
