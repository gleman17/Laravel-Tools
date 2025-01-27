<?php

namespace Gleman17\LaravelTools\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BuildRelationshipsService
{
    private TableRelationshipAnalyzerService $analyzer;
    private RelationshipService $relationshipService;
    private ModelService $modelService;
    private $filesystem;
    private $logger;

    public function __construct(
        ?TableRelationshipAnalyzerService $analyzer,
        ?RelationshipService $relationshipService,
        ?ModelService $modelService,
        $filesystem = null,
        $logger = null
    ) {
        $this->filesystem = $filesystem ?? File::getFacadeRoot();
        $this->logger = $logger ?? Log::getFacadeRoot();
        $this->analyzer = $analyzer  ?? new TableRelationshipAnalyzerService();
        $this->relationshipService = $relationshipService ?? new RelationshipService();
        $this->modelService = $modelService ?? new ModelService();
    }

    /**
     * Validates input parameters and returns error messages if any
     */
    private function validateInput(?string $startModel, ?string $endModel, bool $all): ?string
    {
        if ($startModel && !$all && !$endModel) {
            return "If a start model is provided without an end model, --all must be used.";
        }

        if (!$startModel && !$all) {
            return "Please provide at least a start model or use --all with a starting model, or provide a start and end model.";
        }

        if ($startModel && !$this->modelService->modelExists($startModel)) {
            return "Model $startModel does not exist.";
        }

        if ($endModel && !$this->modelService->modelExists($endModel)) {
            return "Model $endModel does not exist.";
        }

        return null;
    }

    public function build(?string $startModel = null, ?string $endModel = null, bool $all = false): array
    {
        $validationError = $this->validateInput($startModel, $endModel, $all);
        if ($validationError) {
            return [$validationError];
        }

        $messages = [];
        $this->analyzer->analyze();

        if ($startModel && $endModel) {
            return $this->buildSingleRelationship($startModel, $endModel);
        }

        if ($startModel && $all) {
            return $this->buildAllRelationshipsForModel($startModel);
        }

        if (!$startModel && $all) {
            return $this->buildAllRelationships();
        }

        return $messages;
    }

    private function buildSingleRelationship(string $startModel, string $endModel): array
    {
        $this->analyzer->generateRelationship($startModel, $endModel);
        return $this->analyzer->getMessages();
    }

    private function buildAllRelationshipsForModel(string $model): array
    {
        $messages = [];
        $connectedModels = $this->analyzer->findConnectedModels($model);

        if (empty($connectedModels)) {
            return ["No connected models found for $model"];
        }

        foreach ($connectedModels as $connectedModel) {
            $this->analyzer->generateRelationship($model, $connectedModel);
            $messages = array_merge($messages, $this->analyzer->getMessages());
        }

        return $messages;
    }

    private function buildAllRelationships(): array
    {
        $messages = [];
        $models = $this->modelService->getModelNames();

        foreach ($models as $modelA) {
            $connectedModels = $this->analyzer->findConnectedModels($modelA);
            foreach ($connectedModels as $modelB) {
                $this->analyzer->generateRelationship($modelA, $modelB);
                $messages = array_merge($messages, $this->analyzer->getMessages());
            }
        }

        return $messages;
    }

    public function remove(?string $startModel = null, ?string $endModel = null, bool $all = false): array
    {
        $validationError = $this->validateInput($startModel, $endModel, $all);
        if ($validationError) {
            return [$validationError];
        }

        if ($startModel && $endModel) {
            return $this->removeSingleRelationship($startModel, $endModel);
        }

        if ($startModel && $all) {
            return $this->removeAllRelationshipsForModel($startModel);
        }

        if (!$startModel && $all) {
            return $this->removeAllRelationships();
        }

        return [];
    }

    private function removeSingleRelationship(string $startModel, string $endModel): array
    {
        $relationshipNameA = $this->relationshipService->getRelationshipName($endModel, false);
        $this->relationshipService->removeRelationshipFromModel($startModel, $relationshipNameA);

        $relationshipNameB = $this->relationshipService->getRelationshipName($startModel, true);
        $this->relationshipService->removeRelationshipFromModel($endModel, $relationshipNameB);

        return [];
    }

    private function removeAllRelationshipsForModel(string $startModel): array
    {
        $models = $this->modelService->getModelNames();
        foreach ($models as $model) {
            if ($model !== $startModel) {
                $this->removeSingleRelationship($startModel, $model);
            }
        }
        return [];
    }

    private function removeAllRelationships(): array
    {
        $models = $this->modelService->getModelNames();
        foreach ($models as $modelA) {
            foreach ($models as $modelB) {
                if ($modelA !== $modelB) {
                    $this->removeSingleRelationship($modelA, $modelB);
                }
            }
        }
        return [];
    }
}
