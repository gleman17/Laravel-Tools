<?php

namespace Gleman17\LaravelTools;

use Gleman17\LaravelTools\Console\Commands\AnalyzeMethodUsageCommand;
use Gleman17\LaravelTools\Console\Commands\BuildRelationshipsCommand;
use Gleman17\LaravelTools\Console\Commands\CompareTablesWithModelsCommand;
use Gleman17\LaravelTools\Console\Commands\CustomCommandsCommand;
use Gleman17\LaravelTools\Console\Commands\ListModelsCommand;
use Gleman17\LaravelTools\Console\Commands\RemoveRelationshipsCommand;
use Gleman17\LaravelTools\Console\Commands\ClearAICacheCommand;
use Illuminate\Support\ServiceProvider;

class LaravelToolsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gleman17_laravel_tools.php', 'laravel_tools');    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/gleman17_laravel_tools.php' => config_path('gleman17_laravel_tools.php'),
        ], 'gleman17-laravel-tools-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'gleman17-laravel-tools-migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                RemoveRelationshipsCommand::class,
                BuildRelationshipsCommand::class,
                CompareTablesWithModelsCommand::class,
                ListModelsCommand::class,
                AnalyzeMethodUsageCommand::class,
                ClearAICacheCommand::class,
                CustomCommandsCommand::class,
            ]);
        }
    }
}
