<?php

namespace Gleman17\LaravelTools\Services\GenerateRelationship;

class OneStepRelationshipGenerator extends AbstractRelationshipGenerator
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
        $relationshipType = $reverse ? 'belongsTo' : 'hasMany';
        $docBlock = $reverse ? 'BelongsTo' : 'HasMany';
        $foreignKey = "'{$resolvedPath[0]['column']}'";
        $arguments = ["$relatedModelClass::class", "$foreignKey"];
        return $this->generateRelationshipCode($relationshipType, $docBlock, $relationshipName, $relatedModelClass, $arguments);
    }
}
