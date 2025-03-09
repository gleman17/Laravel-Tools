<?php
namespace Gleman17\LaravelTools\Console\Commands;

use Gleman17\LaravelTools\Services\BuildRelationshipsService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class RemoveRelationshipsCommand extends Command
{
    protected $signature = 'gleman_tools:remove-relationships {start?} {end?} {--all}';
    protected $description = 'Removes Eloquent relationships between models.';
    private BuildRelationshipsService $service;

    public function __construct(?BuildRelationshipsService $service = null)
    {
        $this->signature = config('gleman17_laravel_tools.command_signatures.remove_relationships',
                'tools:remove-relationships') .
            ' {start?} {end?} {--all}';
        parent::__construct();
        $this->service = $service ?? new BuildRelationshipsService();
    }

    public function handle(): int
    {
        $startModel = $this->argument('start');
        $endModel = $this->argument('end');
        $all = $this->option('all');

        if (!$startModel && $all) {
            if (!$this->confirm('Are you sure? This will remove ALL relationships from ALL models.')) {
                $this->info('Operation cancelled.');
                return CommandAlias::FAILURE;
            }
        }

        $messages = $this->service->remove($startModel, $endModel, $all);

        foreach ($messages as $message) {
            $this->info($message);
        }

        return CommandAlias::SUCCESS;
    }
}
