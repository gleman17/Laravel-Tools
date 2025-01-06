<?php

namespace Gleman17\LaravelTools;

use Illuminate\Support\ServiceProvider;

class LaravelToolsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gleman17_laravel_tools.php', 'laravel_tools');    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/gleman17_laravel_tools.php' => config_path('gleman17_laravel_tools.php'),
        ], 'gleman17-laravel-tools-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Gleman17\LaravelTools\Console\Commands\RemoveRelationshipsCommand::class,
                \Gleman17\LaravelTools\Console\Commands\BuildRelationshipsCommand::class, // Replace with your commands
                \Gleman17\LaravelTools\Console\Commands\CompareTablesWithModelsCommand::class, // Replace with your commands
                \Gleman17\LaravelTools\Console\Commands\ListModelsCommand::class, // Replace with your commands
            ]);
        }
    }
}
