<?php

namespace Gleman17\LaravelTools\Console\Commands;

use Illuminate\Console\Command;
use Gleman17\LaravelTools\Services\TableModelAnalyzerService;

class CompareTablesWithModelsCommand extends Command
{
    protected $signature;
    protected $description = 'List database tables without corresponding models and optionally create missing models';

    protected TableModelAnalyzerService $service;

    protected $help = <<<EOT
This command checks the database tables and lists those without corresponding models.

Options:
  --make    Automatically create models for tables that are missing models.

Usage:
  php artisan tools:check-tables
  php artisan tools:check-tables --make
EOT;

    public function __construct(TableModelAnalyzerService $service)
    {
        $this->signature = config('gleman17_laravel_tools.command_signatures.compare_tables_with_models',
                'tools:check-tables') .
            ' {--make : Create missing models automatically}';
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $this->info('Detecting database and fetching table names...');
        try {
            $tablesWithoutModels = $this->service->findTablesWithoutModels();

            if (empty($tablesWithoutModels)) {
                $this->info('All tables have corresponding models.');
                return Command::SUCCESS;
            }

            $this->warn('The following tables do not have corresponding models:');
            foreach ($tablesWithoutModels as $table) {
                $this->line("- $table");
            }

            if ($this->option('make')) {
                $this->info('Creating missing models...');
                $results = $this->service->createModelsForTables($tablesWithoutModels);

                foreach ($results as $table => $message) {
                    $this->line($message);
                }
            }
        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
