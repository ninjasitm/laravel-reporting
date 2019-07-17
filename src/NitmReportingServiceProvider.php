<?php

namespace Nitm\Reporting;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nitm\Reporting\Contracts\EntriesRepository;
use Nitm\Reporting\Contracts\PrunableRepository;
use Nitm\Reporting\Contracts\ClearableRepository;
use Nitm\Reporting\Storage\DatabaseEntriesRepository;

class NitmReportingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        Route::middlewareGroup('nitm-reporting', config('nitm-reporting.middleware', []));

        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerPublishing();

        NitmReporting::start($this->app);
        NitmReporting::listenForStorageOpportunities($this->app);

        $this->loadViewsFrom(
            __DIR__.'/../resources/views', 'nitm-reporting'
        );
    }

    /**
     * Register the package routes.
     *
     * @return void
     */
    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        });
        Route::group($this->apiRouteConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/Http/api-routes.php');
        });
    }

    /**
     * Get the Nitm Reporting route group configuration array.
     *
     * @return array
     */
    private function routeConfiguration()
    {
        return [
            'domain' => config('nitm-reporting.domain', null),
            'namespace' => 'Nitm\Reporting\Http\Controllers',
            'prefix' => config('nitm-reporting.path'),
            'middleware' => 'nitm-reporting',
        ];
    }

    /**
     * Get the Nitm Reporting api route group configuration array.
     *
     * @return array
     */
    private function apiRouteConfiguration()
    {
        return config('nitm-reporting.api-route');
    }

    /**
     * Register the package's migrations.
     *
     * @return void
     */
    private function registerMigrations()
    {
        if ($this->app->runningInConsole() && $this->shouldMigrate()) {
            $this->loadMigrationsFrom(__DIR__.'/Storage/migrations');
        }
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    private function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Storage/migrations' => database_path('migrations'),
            ], 'nitm-reporting-migrations');

            $this->publishes([
                __DIR__.'/../public' => public_path('vendor/nitm-reporting'),
            ], 'nitm-reporting-assets');

            $this->publishes([
                __DIR__.'/../config/nitm-reporting.php' => config_path('nitm-reporting.php'),
            ], 'nitm-reporting-config');

            $this->publishes([
                __DIR__.'/../stubs/NitmReportingServiceProvider.stub' => app_path('Providers/NitmReportingServiceProvider.php'),
                __DIR__.'/../stubs/ReportingController.stub' => app_path('Http/Controllers/Api/ReportingController.php'),
            ], 'nitm-reporting-provider');
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/nitm-reporting.php', 'nitm-reporting'
        );

        $this->registerStorageDriver();

        $this->commands([
            Console\InstallCommand::class,
            Console\PruneCommand::class,
            Console\PublishCommand::class,
        ]);
    }

    /**
     * Register the package storage driver.
     *
     * @return void
     */
    protected function registerStorageDriver()
    {
        $driver = config('nitm-reporting.driver');

        if (method_exists($this, $method = 'register'.ucfirst($driver).'Driver')) {
            $this->$method();
        }
    }

    /**
     * Register the package database storage driver.
     *
     * @return void
     */
    protected function registerDatabaseDriver()
    {
        $this->app->singleton(
            EntriesRepository::class, DatabaseEntriesRepository::class
        );

        $this->app->singleton(
            ClearableRepository::class, DatabaseEntriesRepository::class
        );

        $this->app->singleton(
            PrunableRepository::class, DatabaseEntriesRepository::class
        );

        $this->app->when(DatabaseEntriesRepository::class)
            ->needs('$connection')
            ->give(config('nitm-reporting.storage.database.connection'));

        $this->app->when(DatabaseEntriesRepository::class)
            ->needs('$chunkSize')
            ->give(config('nitm-reporting.storage.database.chunk'));
    }

    /**
     * Determine if we should register the migrations.
     *
     * @return bool
     */
    protected function shouldMigrate()
    {
        return NitmReporting::$runsMigrations && config('nitm-reporting.driver') === 'database';
    }
}