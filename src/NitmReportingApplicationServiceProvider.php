<?php

namespace Nitm\Reporting;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class NitmReportingApplicationServiceProvider extends ServiceProvider
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
     * Configure the NitmReporting authorization services.
     *
     * @return void
     */
    protected function authorization()
    {
        $this->gate();

        NitmReporting::auth(function ($request) {
            return app()->environment('local') ||
                   Gate::check('viewNitmReporting', [$request->user()]);
        });
    }

    /**
     * Register the NitmReporting gate.
     *
     * This gate determines who can access NitmReporting in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewNitmReporting', function ($user) {
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
