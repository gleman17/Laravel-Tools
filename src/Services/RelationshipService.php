<?php

namespace Gleman17\LaravelTools\Services;

use PHPSQLParser\PHPSQLParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

        // Handle joins if present in FROM array
        if (count($parsed['FROM']) > 1) {
            $query = $this->handleJoins($query, $baseTable, array_slice($parsed['FROM'], 1));
        }

        // Handle select fields
        $query = $this->handleSelect($query, $parsed['SELECT']);

        // Handle where conditions
        if (isset($parsed['WHERE'])) {
            $query = $this->handleWhere($query, $parsed['WHERE']);
        }

        // Handle group by
        if (isset($parsed['GROUP'])) {
            $query = $this->handleGroupBy($query, $parsed['GROUP']);
        }

        // Handle having
        if (isset($parsed['HAVING'])) {
            $query = $this->handleHaving($query, $parsed['HAVING']);
        }

        // Handle order by
        if (isset($parsed['ORDER'])) {
            $query = $this->handleOrderBy($query, $parsed['ORDER']);
        }

        return $query;
    }

    private function handleJoins(string $query, string $baseTable, array $joins): string
    {
        $this->analyzer->analyze();
        $relationships = [];
        $tables = ['base' => $baseTable];

        foreach ($joins as $join) {
            if (!isset($join['table']) || !isset($join['ref_clause'])) {
                continue;
            }

            $joinTable = $join['table'];

            if (!Schema::hasTable($joinTable)) {
                throw new \RuntimeException("Table does not exist: $joinTable");
            }

            // Analyze the join conditions to determine relationship hierarchy
            $refClause = $join['ref_clause'];
            $leftTable = $refClause[0]['no_quotes']['parts'][0];
            $rightTable = $refClause[2]['no_quotes']['parts'][0];

            if ($leftTable === $baseTable) {
                // Direct relationship with base table
                $relationships[] = $this->getRelationshipNameFromTable($joinTable);
            } elseif (in_array($leftTable, $tables)) {
                // Nested relationship
                $parentRelation = $this->getRelationshipNameFromTable($leftTable);
                $childRelation = $this->getRelationshipNameFromTable($joinTable);
                $relationships[] = $parentRelation . '.' . $childRelation;
            }

            $tables[] = $joinTable;
        }

        // Add relationships to query in correct order
        foreach (array_unique($relationships) as $relation) {
            $query .= "\n    ->with('$relation')";
        }

        return $query;
    }

    private function handleHaving(string $query, array $having): string
    {
        $field = null;
        $operator = null;
        $value = null;

        foreach ($having as $part) {
            switch ($part['expr_type']) {
                case 'alias':
                case 'colref':
                    $field = $part['base_expr'];
                    break;
                case 'operator':
                    $operator = $part['base_expr'];
                    break;
                case 'const':
                    $value = $part['base_expr'];
                    break;
            }
        }

        if ($field && $operator && $value !== null) {
            $query .= "\n    ->having('$field', '$operator', $value)";
        }

        return $query;
    }

    /**
     * Convert table name to model name
     */
    private function getModelNameFromTable(string $table): string
    {
        // Check if the table name ends with a number
        if (preg_match('/^(.+?)(\d+)$/', $table, $matches)) {
            // If it does, singularize only the text part before the number
            $textPart = $matches[1];
            $numberPart = $matches[2];
            $singular = Str::singular($textPart) . $numberPart;
            return 'App\\Models\\' . Str::studly($singular);
        }

        // Otherwise, use the normal singularization
        return 'App\\Models\\' . Str::studly(Str::singular($table));
    }

    /**
     * Convert table name to relationship name (camelCase)
     */
    private function getRelationshipNameFromTable(string $table): string
    {
        // Check if the table name ends with a number
        if (preg_match('/^(.+?)(\d+)$/', $table, $matches)) {
            // If it does, pluralize only the text part before the number
            $textPart = $matches[1];
            $numberPart = $matches[2];
            $plural = Str::plural($textPart) . $numberPart;
            return Str::camel($plural);
        }

        // Otherwise, use the normal pluralization
        return Str::camel(Str::plural($table));
    }

    /**
     * Build a dot-notation relationship path from table path
     */
    private function buildNestedRelationPath(array $path): string
    {
        $relationships = [];
        for ($i = 0; $i < count($path) - 1; $i++) {
            $nextTable = $path[$i + 1];
            $relationships[] = $this->getRelationshipNameFromTable($nextTable);
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
            if (!isset($field['expr_type'])) {
                continue;
            }

            if ($field['expr_type'] === 'colref') {
                if ($field['base_expr'] === '*') {
                    return $query;
                }
                $fields[] = $field['base_expr'];
            } elseif ($field['expr_type'] === 'aggregate_function') {
                $fields[] = $field['base_expr'] . '(' . $field['sub_tree'][0]['base_expr'] . ')' .
                    (isset($field['alias']) ? ' as ' . $field['alias']['name'] : '');
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
            if (isset($field['base_expr'])) {
                $groupFields[] = $field['base_expr'];
            }
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
        if (str_starts_with($modelName, '/')) {
            return $modelName . '.php';
        }

        if (str_contains($modelName, '\\')) {
            $modelPath = str_replace('\\', '/', $modelName);
            $modelPath = str_replace('App/', 'app/', $modelPath);
            return $this->basePath . '/' . $modelPath . '.php';
        }

        if (str_contains($modelName, '/')) {
            return $this->basePath . '/' . $modelName . '.php';
        }

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
