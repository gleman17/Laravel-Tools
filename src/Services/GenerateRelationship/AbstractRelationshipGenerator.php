<?php

namespace Gleman17\LaravelTools\Services\GenerateRelationship;
abstract class AbstractRelationshipGenerator implements RelationshipGeneratorInterface
{
    /**
     * @param string $relationshipType
     * @param string $docBlock
     * @param string $relationshipName
     * @param string $relatedModelClass
     * @param array<string|array<string, string>> $arguments
     * @return string
     */
    protected function generateRelationshipCode(string $relationshipType, string $docBlock, string $relationshipName, string $relatedModelClass, array $arguments): string
    {
        $formattedArguments = RelationshipArgumentFormatter::format($arguments);

        return <<<EOT
    /**
     * $docBlock relationship to $relatedModelClass
     */
    public function $relationshipName()
    {
        return \$this->$relationshipType(
            $formattedArguments
        );
    }
EOT;
    }
}
