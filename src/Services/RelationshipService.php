<?php

namespace Gleman17\LaravelTools\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RelationshipService
{
    protected $fileSystem;
    protected $logger;
    protected $basePath;
    protected $appPath;

    public function __construct($fileSystem = null, $logger = null, $basePath = null, $appPath = null)
    {
        $this->fileSystem = $fileSystem ?: new Filesystem();
        $this->logger = $logger ?: new Log();
        $this->basePath = $basePath ?: base_path();
        $this->appPath = $appPath ?: app_path();
    }

    public function removeRelationshipFromModel(string $modelName, string $relationshipName): bool
    {
        $modelPath = $this->getModelPath($modelName);

        if (!$this->fileSystem->exists($modelPath)) {
            $this->logger::error("Model file not found: {$modelPath}");
            return false;
        }

        $contents = $this->fileSystem->get($modelPath);
        $pattern = '/(\s*\/\*\*[\s\S]*?\*\/\s*)?public\s+function\s+' . preg_quote($relationshipName, '/') . '\s*\(\)[\s\S]*?\n\s*\}/s';

        $newContents = preg_replace($pattern, '', $contents);

        if ($newContents !== $contents) {
            $this->fileSystem->put($modelPath, $newContents);
            return true;
        }

        return false;
    }

    public function getModelPath(string $modelName): string
    {
        // Handle absolute paths
        if (str_starts_with($modelName, '/')) {
            return $modelName . '.php';
        }

        // Handle namespaced paths
        if (str_contains($modelName, '\\')) {
            $modelPath = str_replace('\\', '/', $modelName);
            $modelPath = str_replace(['App/', 'app/'], $this->getAppDirectoryName() . '/', $modelPath);
            return $this->basePath . '/' . $modelPath . '.php';
        }

        // Handle relative paths
        if (str_contains($modelName, '/')) {
            return $this->basePath . '/' . $modelName . '.php';
        }

        // Handle base model names
        return $this->appPath . "/Models/{$modelName}.php";
    }

    public function getAppDirectoryName(): string
    {
        return 'app';
    }

    public function relationshipExistsInModel(string $modelName, string $relationshipName): bool
    {
        $modelPath = $this->getModelPath($modelName);

        if (!$this->fileSystem->exists($modelPath)) {
            return false;
        }

        $content = $this->fileSystem->get($modelPath);
        return preg_match('/public\s+function\s+' . preg_quote($relationshipName, '/') . '\s*\(\)/', $content) > 0;
    }

    public function checkDuplicateRelationships(string $modelName): array
    {
        $modelPath = $this->getModelPath($modelName);

        if (!$this->fileSystem->exists($modelPath)) {
            return [];
        }

        $content = $this->fileSystem->get($modelPath);
        $pattern = '/public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(\)/';

        preg_match_all($pattern, $content, $matches);

        $relationships = [];
        $duplicates = [];

        foreach ($matches[1] as $relationship) {
            if (in_array($relationship, $relationships)) {
                $duplicates[] = $relationship;
            } else {
                $relationships[] = $relationship;
            }
        }

        return array_unique($duplicates);
    }
}
