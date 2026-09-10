<?php

namespace App\Providers;

use App\Models\UnitReport;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('reporting-admin', fn (User $user) => $user->hasReportingRole(['ADMIN']));
        Gate::define('reporting-access', fn (User $user) => $user->hasReportingRole(['ADMIN', 'UNIT', 'PIMPINAN']));
        Gate::define('reporting-view', fn (User $user, UnitReport $report) => $user->hasReportingRole(['ADMIN', 'PIMPINAN']) || ($user->hasReportingRole(['UNIT']) && $user->unit_id === $report->unit_id));
        // Paksa semua URL pakai https di server produksi
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
