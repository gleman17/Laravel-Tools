<?php

namespace Gleman17\LaravelTools\Services\GenerateRelationship;

interface RelationshipGeneratorInterface
{
    /**
     * @param string $relationshipName
     * @param string $relatedModelClass
     * @param array<array<string, string>> $resolvedPath
     * @param bool $reverse
     * @return string
     */
    public function generate(string $relationshipName, string $relatedModelClass, array $resolvedPath, bool $reverse): string;
}
