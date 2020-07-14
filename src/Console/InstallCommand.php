<?php

namespace Nitm\Reporting\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nitm-reporting:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the Reporting resources';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->comment('Publishing Reporting Service Provider...');
        $this->callSilent('vendor:publish', ['--tag' => 'nitm-reporting-provider']);

        $this->comment('Publishing Reporting Assets...');
        $this->callSilent('vendor:publish', ['--tag' => 'nitm-reporting-assets']);

        $this->comment('Publishing Reporting Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'nitm-reporting-config']);

        $this->registerReportingServiceProvider();

        $this->info('Reporting scaffolding installed successfully.');
    }

    /**
     * Register the Reporting service provider in the application configuration file.
     *
     * @return void
     */
    protected function registerReportingServiceProvider()
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        $appConfig = file_get_contents(config_path('app.php'));

        if (Str::contains($appConfig, $namespace . '\\Providers\\ReportingServiceProvider::class')) {
            return;
        }

        $lineEndingCount = [
            "\r\n" => substr_count($appConfig, "\r\n"),
            "\r" => substr_count($appConfig, "\r"),
            "\n" => substr_count($appConfig, "\n"),
        ];

        $eol = array_keys($lineEndingCount, max($lineEndingCount))[0];

        file_put_contents(config_path('app.php'), str_replace(
            "{$namespace}\\Providers\RouteServiceProvider::class," . $eol,
            "{$namespace}\\Providers\RouteServiceProvider::class," . $eol . "        {$namespace}\Providers\ReportingServiceProvider::class," . $eol,
            $appConfig
        ));

        file_put_contents(app_path('Providers/ReportingServiceProvider.php'), str_replace(
            "namespace App\Providers;",
            "namespace {$namespace}\Providers;",
            file_get_contents(app_path('Providers/ReportingServiceProvider.php'))
        ));
    }
}
