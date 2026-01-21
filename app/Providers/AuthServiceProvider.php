<?php

namespace App\Providers;
use App\Policies\JambAdmissionLetterRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

// Models
use App\Models\Exam;

// Policies
use App\Policy\ExamPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Exam::class => ExamPolicy::class,
        JambAdmissionLetterRequest::class => JambAdmissionLetterRequestPolicy::class,
    
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /*
        |--------------------------------------------------------------------------
        | Additional Gates (optional)
        |--------------------------------------------------------------------------
        | You can define extra gates here later if needed.
        |
        | Example:
        | Gate::define('view-cbt-admin', fn ($user) => $user->role === 'superadmin');
        |
        */
    }

}
