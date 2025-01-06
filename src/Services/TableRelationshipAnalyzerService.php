<?php

namespace Gleman17\LaravelTools\Services;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Gleman17\LaravelTools\Services\GenerateRelationship\GenerateRelationshipService;
use SplQueue;

class TableRelationshipAnalyzerService
{
    /** @var array<string, array<string>> */
    public array $adjacencyList = [];
    /** @var array<string, array<string>> */
    public array $columnList = [];
    /** @var array<string> */
    public array $messages = [];
    private GenerateRelationshipService $generateRelationshipService;
    private ModelGeneratorService $modelGeneratorService;
    protected ?string $file;
    protected ?string $log;
    public function __construct(
        ?GenerateRelationshipService $generateRelationshipService = null,
        ?ModelGeneratorService $modelGeneratorService = null,
        ?string $file = null,
        ?string $log = null
    ) {
        $this->file = $file ?: File::class;
        $this->log = $log ?: Log::class;
        $this->generateRelationshipService = $generateRelationshipService ?? new GenerateRelationshipService();
        $this->modelGeneratorService = $modelGeneratorService ?? new ModelGeneratorService();
    }

    public function analyze(): void
    {
        $tables = (new DatabaseTableService())->getDatabaseTables();
        foreach ($tables as $table) {
            $this->adjacencyList[$table] = [];
            $this->columnList[$table] = [];
        }
        foreach ($tables as $table) {
            $this->addTableToGraph($table);
        }
    }

    /**
     * @return string[][]
     */
    public function getGraph(): array
    {
        return $this->adjacencyList;
    }

    /**
     * @param array $graph
     * @return void
     */
    public function setGraph(array $graph): void
    {
        $this->adjacencyList = $graph;
    }

    /**
     * @return string[][]
     */
    public function getColumnList(): array
    {
        return $this->columnList;
    }

    /**
     * @param array $columnList
     * @return void
     */
    public function setColumnList(array $columnList): void
    {
        $this->columnList = $columnList;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    protected function addTableToGraph(string $table): void
    {
        $columns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            if (str_ends_with($column, '_id')) {
                $relatedTable = substr($column, 0, -3);

                if (!$this->modelHasConnections($relatedTable)) {
                    $relatedTable = Str::plural($relatedTable); // Handle plural table names
                    if (!$this->modelHasConnections($relatedTable)) {
                        continue;
                    }
                }

                $this->adjacencyList[$table][$relatedTable] = 1;
                $this->adjacencyList[$relatedTable][$table] = 1;
                $this->columnList[$table][$relatedTable] = $column;
            }
        }
    }

    public function generateRelationship(string $modelA, string $modelB): void
    {
        [$modelAPath, $modelBPath, $startTable, $endTable, $success]
            = $this->get_start_and_end($modelA, $modelB);

        if (!$success) {
            $this->messages[] = "Models $modelA or $modelB do not exist.";
            return;
        }

        $path = $this->findPath($startTable, $endTable);

        if (empty($path)) {
            $this->messages[] = "No path found between $modelA and $modelB.";
            return;
        }

        $resolvedPath = $this->resolvePathWithColumns($path);

        if (!$this->relationshipExists(File::get($modelAPath), $modelB)) {
            $this->addRelationship($modelA, $modelAPath, $modelB, $resolvedPath, false);
        }

        if (!$this->relationshipExists(File::get($modelBPath), $modelA)) {
            $this->addRelationship($modelB, $modelBPath, $modelA, array_reverse($resolvedPath), true);
        }
    }

    /**
     * @param array<string> $path
     * @return array<array{
     *     table: string,
     *     next_table: string,
     *     column: string,
     *     next_column: string,
     *     local_key: string,
     *     through_local_key: string
     * }>
     */
    public function resolvePathWithColumns(array $path): array
    {
        $resolvedPath = [];
        for ($i = 0; $i < count($path) - 1; $i++) {
            $currentTable = $path[$i];
            $nextTable = $path[$i + 1];

            $column = $this->columnList[$currentTable][$nextTable] ?? null;
            $nextColumn = $this->columnList[$nextTable][$currentTable] ?? null;

            if (!$column && !$nextColumn) {
                $this->messages[] = "Column information not found for $currentTable to $nextTable.";
                return [];
            }

            $localKey = Schema::getColumnListing($currentTable);
            $localKey = in_array('id', $localKey) ? 'id' : $localKey[0];

            $throughLocalKey = Schema::getColumnListing($nextTable);
            $throughLocalKey = in_array('id', $throughLocalKey) ? 'id' : $throughLocalKey[0];

            $resolvedPath[] = [
                'table' => $currentTable,
                'next_table' => $nextTable,
                'column' => $column ?? $localKey, // use local key if column is null
                'next_column' => $nextColumn ?? $throughLocalKey, // use through local key if next column is null
                'local_key' => $localKey,
                'through_local_key' => $throughLocalKey,
            ];
        }
        return $resolvedPath;
    }


    protected function getModelPath(string $model): ?string
    {
        $modelPath = app_path('Models/' . class_basename($model) . '.php');
        return File::exists($modelPath) ? $modelPath : null;
    }

    protected function relationshipExists(string $content, string $relatedModel): bool
    {
        $relationshipName = Str::camel(class_basename($relatedModel));
        $pattern = "/public\s+function\s+{$relationshipName}\s*\(/i";
        return preg_match($pattern, $content) === 1;
    }

    /**
     * Checks if the resolved path is acceptable.
     *
     * @param array<array{
     *     table: string,
     *     next_table: string,
     *     column: string,
     *     next_column: string,
     *     local_key: string,
     *     through_local_key: string
     * }> $resolvedPath
     * @return bool
     */
    protected function resolvedPathIsAcceptable(array $resolvedPath): bool
    {
        if (empty($resolvedPath)) {
            return false;
        }

        $firstPathElement = $resolvedPath[0];

        if (!array_key_exists('column', $firstPathElement) || ($firstPathElement['column'] ?? null) === null) {
            return false;
        }

        return true;
    }

    /**
     * @param string $model
     * @param string $modelPath
     * @param string $relatedModel
     * @param array<array{
     *     table: string,
     *     next_table: string,
     *     column: string,
     *     next_column: string,
     *     local_key: string,
     *     through_local_key: string
     * }> $resolvedPath
     * @param bool $reverse
     * @return void
     * @throws FileNotFoundException
     */
    protected function addRelationship(string $model, string $modelPath, string $relatedModel, array $resolvedPath, bool $reverse = false): void
    {

        if (!$this->resolvedPathIsAcceptable($resolvedPath)) {
            $this->messages[] = "Resolved Path not acceptable for $model to $relatedModel.";
            return;
        }

        $relationshipName = $reverse ? Str::singular(Str::camel($relatedModel)) : Str::plural(Str::camel($relatedModel));
        $relatedModelClass = '\\App\\Models\\' . $relatedModel;

        $relationshipMethod = $this->generateRelationshipService->generateRelationshipMethod(
            $relationshipName,
            $relatedModelClass,
            $reverse,
            $resolvedPath
        );

        $updatedContent = preg_replace('/}\s*$/', "    $relationshipMethod\n}\n", File::get($modelPath));
        File::put($modelPath, $updatedContent);
    }

    /**
     * @param string $startTable
     * @param string $endTable
     * @return array<string>
     */
    public function findPath(string $startTable, string $endTable): array
    {
        $queue = new \SplQueue();
        $queue->enqueue([$startTable]);
        $visited = [$startTable];

        while (!$queue->isEmpty()) {
            $path = $queue->dequeue();
            $current = end($path);

            if ($current === $endTable) {
                return $path;
            }

            if ($this->modelHasConnections($current)) {
                foreach (array_keys($this->adjacencyList[$current]) as $neighbor) {
                    if (!in_array($neighbor, $visited)) {
                        $newPath = $path;
                        $newPath[] = $neighbor;
                        $queue->enqueue($newPath);
                        $visited[] = $neighbor;
                    }
                }
            }
        }

        return [];
    }

    /**
     * @param string|null $modelPath
     * @param string $modelName
     * @return array{0: string|null, 1: bool}
     */
    public function checkModelPath(?string $modelPath, string $modelName): array
    {
        if (!$modelPath) {
            try {
                $this->modelGeneratorService->ensureModelExists($modelName);
                $modelPath = $this->getModelPath($modelName);
                $this->messages[] = "Generated model: $modelName";
            } catch (\Exception $e) {
                $this->messages[] = "Failed to generate model $modelName: " . $e->getMessage();
                return [null, false]; // Return null path and false on failure
            }
        }

       return [$modelPath, !empty($modelPath)];
    }

    /**
     * @param string $modelA
     * @param string $modelB
     * @return array{0: string|null, 1: string|null, 2: string|null, 3: string|null, 4: bool}
     */
    public function get_start_and_end(string $modelA, string $modelB): array
    {
        $modelAPath = $this->getModelPath($modelA);
        [$modelAPath, $success] = $this->checkModelPath($modelAPath, $modelA);
        if (!$success) {
            $this->messages[] = "$modelA model path does not exist.";
            return [null, null, null, null, false];
        }

        $modelBPath = $this->getModelPath($modelB);
        [$modelBPath, $success] = $this->checkModelPath($modelBPath, $modelB);
        if (!$success) {
            $this->messages[] = "$modelB model path does not exist.";
            return [null, null, null, null, false];
        }

        $startTable = (new ("App\\Models\\$modelA"))->getTable();
        $endTable = (new ("App\\Models\\$modelB"))->getTable();

        return [$modelAPath, $modelBPath, $startTable, $endTable, true];
    }

    /**
     * Finds all models connected to a given model name in the adjacency list.
     *
     * @param string $modelName The name of the model to find connections for.
     * @return array<string> An array of connected model names, or an empty array if none are found.
     */
    public function findConnectedModels(string $modelName): array
    {
        $tableName = Str::snake(Str::plural($modelName));
        $connectedModels = [];
        $visited = [];
        $queue = new SplQueue();

        if ($this->modelHasConnections($tableName)) {
            $queue->enqueue($tableName);
            $visited[$tableName] = true;

            while (!$queue->isEmpty()) {
                $currentTable = $queue->dequeue();

                if ($this->modelHasConnections($currentTable)) {
                    foreach (array_keys($this->adjacencyList[$currentTable]) as $neighborTable) {
                        if (!isset($visited[$neighborTable])) {
                            [$connectedModels, $visited] = $this->markAsVisited($neighborTable, $connectedModels, $queue, $visited);
                        }
                    }
                }
            }
        }

        return array_unique($connectedModels);
    }

    /**
     * @param string $tableName
     * @return bool
     */
    public function modelHasConnections(string $tableName): bool
    {
        return isset($this->adjacencyList[$tableName]);
    }

    /**
     * Marks a neighbor table as visited and updates the connected models and queue.
     *
     * @param string $neighborTable The name of the neighbor table.
     * @param array<string> $connectedModels The array of connected model names.
     * @param SplQueue<string> $queue The queue for breadth-first search.
     * @param array<string, bool> $visited An associative array of visited tables.
     * @return array{0: array<string>, 1: array<string, bool>} An array containing the updated connected models and visited tables.
     */
    public function markAsVisited(string $neighborTable, array $connectedModels, SplQueue $queue, array $visited): array
    {
        $neighborModel = Str::studly(Str::singular($neighborTable));
        $connectedModels[] = $neighborModel;
        $queue->enqueue($neighborTable);
        $visited[$neighborTable] = true;
        return [$connectedModels, $visited];
    }

}
