<?php

namespace Gleman17\LaravelTools\Console\Commands;

use Gleman17\LaravelTools\Services\ModelService;
use Gleman17\LaravelTools\Services\TableRelationshipAnalyzerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ListModelsCommand extends Command
{
    protected $signature = 'gleman_tools:list_models';
    protected $description = 'List all models in the project';
    private ModelService $modelService;
    private TableRelationshipAnalyzerService $analyzer;

    public function __construct()
    {
        $this->signature = config('gleman17_laravel_tools.command_signatures.list_models',
                'tools:list-models');

        parent::__construct();
        $this->modelService = new ModelService($this);
        $this->analyzer = new TableRelationshipAnalyzerService();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        $models = $this->modelService->getModelNames();
        $this->analyzer->analyze();

        $this->line("Models Found:");
        foreach ($models as $model) {
            $this->line($model);
            $connectedTables = $this->analyzer->getConnectedTables($model);
            foreach ($connectedTables as $connectedTable) {
                $connectedModel = str_replace('_', '', Str::singular(Str::studly( $connectedTable)));
                $color = $this->modelService->modelExists($connectedModel) ? '' : '<fg=yellow>';
                $endColor = empty($color) ? '' : '</>';
                $this->line("  - $color $connectedModel $endColor");
            }
        }
    }
}
