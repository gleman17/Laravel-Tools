<?php

namespace Gleman17\LaravelTools\Services;

use PHPSQLParser\PHPSQLParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RelationshipService
{
    protected $fileSystem;
    protected $logger;
    protected $basePath;
    protected $appPath;

    private TableRelationshipAnalyzerService $analyzer;

    public function __construct($fileSystem = null, $logger = null, $basePath = null, $appPath = null)
    {
        $this->fileSystem = $fileSystem ?: new Filesystem();
        $this->logger = $logger ?: new Log();
        $this->basePath = $basePath ?: base_path();
        $this->appPath = $appPath ?: app_path();
        $this->analyzer = new TableRelationshipAnalyzerService();
    }

    /**
     * Convert an SQL query to its Eloquent equivalent
     *
     * @param string $sql The SQL query to convert
     * @return string The equivalent Eloquent query
     */
    public function sqlToEloquent(string $sql): string
    {
        $parser = new PHPSQLParser();
        $parsed = $parser->parse($sql);

        if (!isset($parsed['FROM']) || !isset($parsed['SELECT'])) {
            throw new \InvalidArgumentException('Invalid SQL query. Must contain SELECT and FROM clauses.');
        }

        // Get the base table and its model name
        $baseTable = $parsed['FROM'][0]['table'];
        $baseModel = $this->getModelNameFromTable($baseTable);
        $query = "$baseModel::query()";

        // Handle joins if present
        if (isset($parsed['JOIN'])) {
            $query = $this->handleJoins($query, $baseTable, $parsed['JOIN']);
        }

        // Handle select fields
        $query = $this->handleSelect($query, $parsed['SELECT']);

        // Handle where conditions
        if (isset($parsed['WHERE'])) {
            $query = $this->handleWhere($query, $parsed['WHERE']);
        }

        // Handle order by
        if (isset($parsed['ORDER'])) {
            $query = $this->handleOrderBy($query, $parsed['ORDER']);
        }

        // Handle group by
        if (isset($parsed['GROUP'])) {
            $query = $this->handleGroupBy($query, $parsed['GROUP']);
        }

        return $query;
    }

    /**
     * Convert table name to model name
     */
    private function getModelNameFromTable(string $table): string
    {
        return 'App\\Models\\' . Str::studly(Str::singular($table));
    }

    /**
     * Handle SQL JOINs by converting them to Eloquent relationship calls
     */
    private function handleJoins(string $query, string $baseTable, array $joins): string
    {
        $baseModel = $this->getModelNameFromTable($baseTable);

        foreach ($joins as $join) {
            $joinTable = $join['table'];
            $joinModel = $this->getModelNameFromTable($joinTable);

            // Find the path between the models using the analyzer
            $path = $this->analyzer->findPath($baseTable, $joinTable);

            if (empty($path)) {
                throw new \RuntimeException("No relationship path found between $baseTable and $joinTable");
            }

            // If it's a direct relationship (path length 2)
            if (count($path) === 2) {
                $relationName = $this->getRelationshipName($joinModel, false);
                $query .= "\n    ->with('$relationName')";
            } else {
                // For nested relationships, we need to chain the relationships
                $relationPath = $this->buildRelationshipPath($path);
                $query .= "\n    ->with('$relationPath')";
            }
        }

        return $query;
    }

    /**
     * Build a dot-notation relationship path from table path
     */
    private function buildRelationshipPath(array $path): string
    {
        $relationships = [];
        for ($i = 0; $i < count($path) - 1; $i++) {
            $currentTable = $path[$i];
            $nextTable = $path[$i + 1];
            $nextModel = $this->getModelNameFromTable($nextTable);
            $relationships[] = $this->getRelationshipName($nextModel, false);
        }

        return implode('.', $relationships);
    }

    /**
     * Handle SELECT clause
     */
    private function handleSelect(string $query, array $select): string
    {
        $fields = [];
        foreach ($select as $field) {
            if ($field['expr_type'] === 'colref') {
                $fields[] = $field['base_expr'];
            }
        }

        if (!empty($fields)) {
            $fieldsList = implode("', '", $fields);
            $query .= "\n    ->select('$fieldsList')";
        }

        return $query;
    }

    /**
     * Handle WHERE clause
     */
    private function handleWhere(string $query, array $where): string
    {
        $conditions = $this->parseWhereConditions($where);
        foreach ($conditions as $condition) {
            $query .= "\n    ->where('{$condition['field']}', '{$condition['operator']}', {$condition['value']})";
        }
        return $query;
    }

    /**
     * Parse WHERE conditions into a structured format
     */
    private function parseWhereConditions(array $where): array
    {
        $conditions = [];
        $currentCondition = [];

        foreach ($where as $token) {
            if ($token['expr_type'] === 'colref') {
                $currentCondition['field'] = $token['base_expr'];
            } elseif ($token['expr_type'] === 'operator') {
                $currentCondition['operator'] = $token['base_expr'];
            } elseif ($token['expr_type'] === 'const') {
                $currentCondition['value'] = $token['base_expr'];
                $conditions[] = $currentCondition;
                $currentCondition = [];
            }
        }

        return $conditions;
    }

    /**
     * Handle ORDER BY clause
     */
    private function handleOrderBy(string $query, array $order): string
    {
        foreach ($order as $orderBy) {
            $direction = $orderBy['direction'] ?? 'ASC';
            $query .= "\n    ->orderBy('{$orderBy['base_expr']}', '$direction')";
        }
        return $query;
    }

    /**
     * Handle GROUP BY clause
     */
    private function handleGroupBy(string $query, array $group): string
    {
        $groupFields = [];
        foreach ($group as $field) {
            $groupFields[] = $field['base_expr'];
        }

        if (!empty($groupFields)) {
            $fieldsList = implode("', '", $groupFields);
            $query .= "\n    ->groupBy('$fieldsList')";
        }

        return $query;
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
            $modelPath = str_replace('App/', 'app/', $modelPath);
            return $this->basePath . '/' . $modelPath . '.php';
        }

        // Handle relative paths
        if (str_contains($modelName, '/')) {
            return $this->basePath . '/' . $modelName . '.php';
        }

        // Handle base model names
        return $this->appPath . "/Models/{$modelName}.php";
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
