<?php

namespace Gleman17\LaravelTools\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class QueryBuilderService
{
    private array $relationships = [];
    private array $tableMetadata = [];
    private array $columnMappings = [];
    private DatabaseTableService $databaseTableService;
    private TableRelationshipAnalyzerService $relationshipAnalyzer;

    private array $commonTimeframes = [
        'today' => 'subDay',
        'yesterday' => 'subDays',
        'this week' => 'startOfWeek',
        'last week' => 'subWeek',
        'this month' => 'startOfMonth',
        'last month' => 'subMonth',
        'this year' => 'startOfYear',
        'last year' => 'subYear'
    ];

    private array $aggregateFunctions = [
        'count' => ['words' => ['count', 'number of', 'how many'], 'function' => 'count'],
        'sum' => ['words' => ['sum', 'total', 'sum of'], 'function' => 'sum'],
        'avg' => ['words' => ['average', 'avg', 'mean'], 'function' => 'avg'],
        'max' => ['words' => ['maximum', 'max', 'highest', 'most recent'], 'function' => 'max'],
        'min' => ['words' => ['minimum', 'min', 'lowest', 'earliest'], 'function' => 'min']
    ];

    public function __construct(
        DatabaseTableService $databaseTableService,
        TableRelationshipAnalyzerService $relationshipAnalyzer
    ) {
        $this->databaseTableService = $databaseTableService;
        $this->relationshipAnalyzer = $relationshipAnalyzer;

        // Initialize metadata after setting dependencies
        $this->relationshipAnalyzer->analyze();
        $this->columnMappings = $this->relationshipAnalyzer->getColumnList();
        $this->relationships = $this->convertGraphToRelationships($this->relationshipAnalyzer->getGraph());
        $this->tableMetadata = $this->databaseTableService->getMetadata();
    }

    private function convertGraphToRelationships(array $graph): array
    {
        info('Converting graph: '.json_encode($graph));
        $relationships = [];
        foreach ($graph as $table => $connections) {
            $modelName = $this->databaseTableService->tableToModelName($table);
            info('Converting table: '.$table.' to model: '.$modelName);
            $relationships[$modelName] = [];

            foreach ($connections as $relatedTable => $value) {
                if ($value) {
                    $relatedModel = $this->databaseTableService->tableToModelName($relatedTable);
                    info('Looking up foreign key for: '.$table.' -> '.$relatedTable);
                    info('Column mappings: '.json_encode($this->columnMappings));

                    $fromTable = strtolower($table);
                    $toTable = strtolower($relatedTable);
                    $foreignKey = $this->columnMappings[$fromTable][$toTable] ?? null;

                    info('Using fromTable: '.$fromTable.', toTable: '.$toTable);
                    info('Foreign key found: '.($foreignKey ?? 'null'));

                    if ($foreignKey) {
                        $relationshipName = $this->databaseTableService->foreignKeyToRelationName($foreignKey);
                        $relationships[$modelName][$relatedModel] = $relationshipName;
                        info('Added relationship: '.$modelName.' -> '.$relatedModel.' as '.$relationshipName);
                    }
                }
            }
        }
        info('Final relationships: '.json_encode($relationships));
        return $relationships;
    }

    public function generateEloquentQueryCode(string $description): string
    {
        $parsedQuery = $this->parseQueryDescription($description);

        $code = "\$query = App\\Models\\{$parsedQuery['mainEntity']}::query();\n";

        // Generate joins
        foreach ($parsedQuery['joins'] as $join) {
            $code .= "\$query->join('"
                . Str::plural(Str::snake($join['to'])) . "', '"
                . Str::plural(Str::snake($join['from'])) . ".id', '=', '"
                . Str::plural(Str::snake($join['to'])) . "." . $join['foreign_key'] . "');\n";
        }

        // Generate conditions
        foreach ($parsedQuery['conditions'] as $condition) {
            $code .= "\$query->having(DB::raw('count(*)'), '{$condition['operator']}', {$condition['value']});\n";
        }

        // Generate time constraints
        foreach ($parsedQuery['timeConstraints'] as $constraint) {
            $dateField = $this->determineDateField($parsedQuery['mainEntity']);
            $code .= "\$query->where('{$dateField}', '>=', now()->{$constraint['method']}());\n";
        }

        return $code;
    }


    public function buildQueryFromDescription(string $description): Builder
    {
        $query = $this->parseQueryDescription($description);
        return $this->buildEloquentQuery($query);
    }

    private function parseQueryDescription(string $description): array
    {
        $description = strtolower($description);

        return [
            'mainEntity' => $this->identifyMainEntity($description),
            'joins' => $this->identifyJoins($description),
            'conditions' => $this->identifyConditions($description),
            'aggregations' => $this->identifyAggregations($description),
            'timeConstraints' => $this->identifyTimeConstraints($description)
        ];
    }

    private function identifyMainEntity(string $description): string
    {
        $words = explode(' ', $description);
        foreach ($this->relationships as $entity => $relations) {
            $tableName = Str::plural(Str::snake($entity));
            foreach ($words as $word) {
                if (strtolower($word) === strtolower($tableName)) {
                    return $entity;
                }
            }
        }

        throw new InvalidArgumentException("Could not identify main entity in query description");
    }

    private function identifyJoins(string $description): array
    {
        info('description: '.$description);
        $joins = [];
        $words = explode(' ', strtolower($description));
        info('words: '.json_encode($words));

        // Find the main table that appears first in the description
        $mainEntity = null;
        $mainEntityPosition = PHP_INT_MAX;
        foreach ($this->relationships as $entity => $relations) {
            $tableName = strtolower(Str::plural(Str::snake($entity)));
            $position = strpos(strtolower($description), $tableName);
            if ($position !== false && $position < $mainEntityPosition) {
                $mainEntityPosition = $position;
                $mainEntity = $entity;
            }
        }

        if (!$mainEntity || empty($this->relationships[$mainEntity])) {
            return [];
        }

        info('mainEntity: '.$mainEntity);
        info('relations: '.json_encode($this->relationships[$mainEntity]));

        foreach ($this->relationships[$mainEntity] as $toEntity => $relationName) {
            $toTable = strtolower(Str::plural(Str::snake($toEntity)));
            if (str_contains(strtolower($description), $toTable)) {
                $fromTable = Str::plural(Str::snake($mainEntity));
                $foreignKey = $this->columnMappings[strtolower($fromTable)][strtolower($toTable)] ?? null;

                if ($foreignKey) {
                    $joins[] = [
                        'from' => $mainEntity,
                        'to' => $toEntity,
                        'relation' => $relationName,
                        'foreign_key' => $foreignKey
                    ];
                }
            }
        }

        info('returning: '.json_encode($joins));
        return $joins;
    }

    private function identifyConditions(string $description): array
    {
        $conditions = [];

        // Avoid time-related phrases
        $timePhrases = array_keys($this->commonTimeframes);

        // Remove time phrases from the description before parsing conditions
        foreach ($timePhrases as $phrase) {
            $description = str_replace($phrase, '', $description);
        }

        // Look for "more than" comparisons
        if (preg_match_all('/(?:more|greater) than (\d+)/', $description, $moreMatches)) {
            foreach ($moreMatches[1] as $value) {
                $conditions[] = ['type' => 'numeric', 'operator' => '>', 'value' => $value];
            }
        }

        // Look for "less than" comparisons
        if (preg_match_all('/(?:less|fewer) than (\d+)/', $description, $lessMatches)) {
            foreach ($lessMatches[1] as $value) {
                $conditions[] = ['type' => 'numeric', 'operator' => '<', 'value' => $value];
            }
        }

        // Look for equality conditions
        preg_match_all('/(?:is|equals|=) (\w+)/', $description, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $value) {
                $conditions[] = ['type' => 'equality', 'operator' => '=', 'value' => $value];
            }
        }

        return $conditions;
    }


    private function identifyAggregations(string $description): array
    {
        $aggregations = [];

        foreach ($this->aggregateFunctions as $type => $config) {
            foreach ($config['words'] as $word) {
                if (str_contains($description, $word)) {
                    foreach ($this->relationships as $entity => $relations) {
                        $tableName = Str::plural(Str::snake($entity));
                        if (str_contains($description, $tableName)) {
                            $aggregations[] = [
                                'type' => $type,
                                'function' => $config['function'],
                                'table' => $tableName
                            ];
                        }
                    }
                }
            }
        }

        return $aggregations;
    }

    private function identifyTimeConstraints(string $description): array
    {
        $constraints = [];
        $lowerDesc = strtolower($description);

        // Maintain order based on appearance in description
        foreach ($this->commonTimeframes as $timeframe => $method) {
            $position = strpos($lowerDesc, $timeframe);
            if ($position !== false) {
                $constraints[] = [
                    'position' => $position,
                    'timeframe' => $timeframe,
                    'method' => $method
                ];
            }
        }

        // Sort by position of appearance
        usort($constraints, function($a, $b) {
            return $a['position'] - $b['position'];
        });

        // Remove position from final output
        return array_map(function($constraint) {
            unset($constraint['position']);
            return $constraint;
        }, $constraints);
    }

    private function buildEloquentQuery(array $parsedQuery): Builder
    {
        $modelName = $parsedQuery['mainEntity'];
        $query = ("App\\Models\\$modelName")::query();

        // Add joins
        foreach ($parsedQuery['joins'] as $join) {
            $query->join(
                Str::plural(Str::snake($join['to'])),
                $join['from'] . '.id',
                '=',
                $join['foreign_key']
            );
        }

        // Add conditions
        foreach ($parsedQuery['conditions'] as $condition) {
            if ($condition['type'] === 'numeric') {
                $query->having(DB::raw('count(*)'), $condition['operator'], $condition['value']);
            } else {
                // Attempt to determine the appropriate column for the condition
                $column = $this->determineColumnForCondition($parsedQuery['mainEntity'], $condition['value']);
                $query->where($column, $condition['operator'], $condition['value']);
            }
        }

        // Add aggregations with proper grouping
        if (!empty($parsedQuery['aggregations'])) {
            foreach ($parsedQuery['aggregations'] as $aggregation) {
                $query->select(DB::raw("{$aggregation['function']}(*) as {$aggregation['type']}_{$aggregation['table']}"));
            }
            // Add group by if needed
            $query->groupBy($parsedQuery['mainEntity'] . '.id');
        }

        // Add time constraints with configurable date field
        foreach ($parsedQuery['timeConstraints'] as $timeConstraint) {
            $dateField = $this->determineDateField($parsedQuery['mainEntity']);
            $query->where($dateField, '>=', now()->{$timeConstraint['method']}());
        }

        return $query;
    }

    private function determineColumnForCondition(string $entity, string $value): string
    {
        $tableName = Str::plural(Str::snake($entity));
        $metadata = $this->tableMetadata[$tableName] ?? [];

        // Try to find a matching column name
        foreach ($metadata as $columnName => $columnData) {
            if (str_contains(strtolower($columnName), strtolower($value))) {
                return "$tableName.$columnName";
            }
        }

        // Default to id if no match found
        return "$tableName.id";
    }

    private function determineDateField(string $entity): string
    {
        $tableName = Str::plural(Str::snake($entity));
        $metadata = $this->tableMetadata[$tableName] ?? [];

        // Look for common date field names
        $dateFields = ['created_at', 'updated_at', 'date', 'timestamp'];
        foreach ($dateFields as $field) {
            if (isset($metadata[$field])) {
                return "$tableName.$field";
            }
        }

        // Default to created_at
        return "$tableName.created_at";
    }
}
