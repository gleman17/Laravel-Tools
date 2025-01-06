<?php

namespace Gleman17\LaravelTools\Console\Commands;

use Gleman17\LaravelTools\Services\ModelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ListModelsCommand extends Command
{
    protected $signature = 'gleman_tools:list_models';
    protected $description = 'List all models in the project';
    private ModelService $modelService;

    public function __construct()
    {
        $this->signature = config('gleman17_laravel_tools.command_signatures.list_models',
                'tools:list-models');

        parent::__construct();
        $this->modelService = new ModelService($this);
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        $models = $this->modelService->getModelNames();
        $this->line("Models Found:");
        foreach ($models as $model) {
            $this->line($model);
        }
    }
}
