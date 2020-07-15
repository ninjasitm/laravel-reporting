<?php

namespace Nitm\Reporting;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ReportingApplicationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->authorization();
    }

    /**
     * Configure the Reporting authorization services.
     *
     * @return void
     */
    protected function authorization()
    {
        $this->gate();

        Reporting::auth(function ($request) {
            return app()->environment('local') ||
                Gate::check('viewReporting', [$request->user()]);
        });
    }

    /**
     * Register the Reporting gate.
     *
     * This gate determines who can access Reporting in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewReporting', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
