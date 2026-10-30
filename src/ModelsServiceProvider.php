<?php

namespace KusikusiCMS\Models;

use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use KusikusiCMS\Models\Support\IdGenerator;
use KusikusiCMS\Models\Support\ShortIdGenerator;

class ModelsServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/models.php', 'kusikusicms.models');

        // Bind the ID generator so tests (and apps) can override it if needed
        $this->app->singleton(IdGenerator::class, function () {
            return new ShortIdGenerator;
        });
    }

    /**
     * Boots the service by publishing the package's configuration file to the application's configuration path.
     */
    public function boot(): void
    {
        AboutCommand::add('KusikusiCMS core models package', fn () => ['Version' => $this->packageVersion()]);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/models.php' => config_path('kusikusicms/models.php'),
            ], ['kusikusicms-config', 'kusikusicms-models-config']);

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'kusikusicms-models-migrations');
        }
    }

    /**
     * Resolve the installed package version from Composer, falling back to "dev".
     */
    protected function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('kusikusicms/models')) {
            return InstalledVersions::getPrettyVersion('kusikusicms/models') ?? 'dev';
        }

        return 'dev';
    }
}
