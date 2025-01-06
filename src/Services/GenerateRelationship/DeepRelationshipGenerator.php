<?php

namespace Gleman17\LaravelTools\Services\GenerateRelationship;

use Illuminate\Support\Str;

class DeepRelationshipGenerator extends AbstractRelationshipGenerator
{
    /**
     * @param string $relationshipName
     * @param string $relatedModelClass
     * @param array<array<string, string>> $resolvedPath
     * @param bool $reverse
     * @return string
     */
    public function generate(string $relationshipName, string $relatedModelClass, array $resolvedPath, bool $reverse): string
    {
        $intermediateModels = [];
        $foreignKeys = [];
        $localKeys = [];

        // Correct loop: iterate through ALL relations
        for ($i = 0; $i < count($resolvedPath); $i++) {
            $model = '\\App\\Models\\' . Str::studly(Str::singular($resolvedPath[$i]['next_table'] ?? $resolvedPath[$i]['table']));
            if($i < count($resolvedPath) -1) {
                $intermediateModels[] = $model;
            }
            $foreignKeys[] = "'{$resolvedPath[$i]['column']}'";
            $localKeys[] = "'{$resolvedPath[$i]['through_local_key']}'";
        }

        $relationshipType = $reverse ? 'belongsToDeep' : 'hasManyDeep';
        $docBlock = $reverse ? 'BelongsToDeep' : 'HasManyDeep';

        $arguments = [
            "$relatedModelClass::class",
            "[" . implode(', ', array_map(function($model){ return $model."::class";}, $intermediateModels)) . "]",
            "[" . implode(', ', $foreignKeys) . "]",
            "[" . implode(', ', $localKeys) . "]",
        ];

        return $this->generateRelationshipCode($relationshipType, $docBlock, $relationshipName, $relatedModelClass, $arguments);
    }
}
