<?php

namespace App\Providers;

use App\Services\AuditLogService;
use App\Services\BranchAccessService;
use App\Services\DocumentSequenceService;
use App\Services\FeatureManagerService;
use App\Services\FileStorageService;
use App\Services\SubscriptionService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContextService::class, function () {
            return new TenantContextService();
        });

        $this->app->singleton(TenantAuthorizationService::class, function ($app) {
            return new TenantAuthorizationService($app->make(TenantContextService::class));
        });

        $this->app->singleton(BranchAccessService::class, function ($app) {
            return new BranchAccessService($app->make(TenantContextService::class));
        });

        $this->app->singleton(AuditLogService::class, function () {
            return new AuditLogService();
        });

        $this->app->singleton(DocumentSequenceService::class, function ($app) {
            return new DocumentSequenceService($app->make(TenantContextService::class));
        });

        $this->app->singleton(FileStorageService::class, function ($app) {
            return new FileStorageService($app->make(TenantContextService::class));
        });

        $this->app->singleton(FeatureManagerService::class, function () {
            return new FeatureManagerService();
        });

        $this->app->singleton(SubscriptionService::class, function () {
            return new SubscriptionService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Super Admin bypass: platform admins have global authority across platform features
        Gate::before(function ($user, $ability) {
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }
            return null;
        });

        // Dynamic permission check based on User role/permissions
        Gate::define('access-branch', function ($user, int $branchId) {
            return app(BranchAccessService::class)->userHasBranchAccess($user, $branchId);
        });

        Gate::define('manage-tenant-users', function ($user) {
            return $user->hasPermission('manage_users') || $user->isTenantAdmin();
        });
    }
}

