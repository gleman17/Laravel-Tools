<?php

namespace Gleman17\LaravelTools\Services\GenerateRelationship;

class GenerateRelationshipService
{
    private OneStepRelationshipGenerator $oneStepGenerator;
    private TwoStepRelationshipGenerator $twoStepGenerator;
    private DeepRelationshipGenerator $deepGenerator;

    public function __construct(
        ?OneStepRelationshipGenerator $oneStepGenerator=null,
        ?TwoStepRelationshipGenerator $twoStepGenerator=null,
        ?DeepRelationshipGenerator $deepGenerator=null
    ) {
        $this->oneStepGenerator = $oneStepGenerator ?? new OneStepRelationshipGenerator();
        $this->twoStepGenerator = $twoStepGenerator ?? new TwoStepRelationshipGenerator();
        $this->deepGenerator = $deepGenerator ?? new DeepRelationshipGenerator();
    }

    /**
     * @param string $relationshipName
     * @param string $relatedModelClass
     * @param bool $reverse
     * @param array<array<string, string>> $resolvedPath
     * @return string
     * @throws \InvalidArgumentException If the resolved path is empty
     */
    public function generateRelationshipMethod(
        string $relationshipName,
        string $relatedModelClass,
        bool $reverse,
        array $resolvedPath
    ): string {
        if (empty($resolvedPath)) {
            throw new \InvalidArgumentException('Resolved path cannot be empty');
        }

        return match(count($resolvedPath)) {
            1 => $this->oneStepGenerator->generate(
                $relationshipName,
                $relatedModelClass,
                $resolvedPath,
                $reverse
            ),
            2 => $this->twoStepGenerator->generate(
                $relationshipName,
                $relatedModelClass,
                $resolvedPath,
                $reverse
            ),
            default => $this->deepGenerator->generate(
                $relationshipName,
                $relatedModelClass,
                $resolvedPath,
                $reverse
            ),
        };
    }
}
