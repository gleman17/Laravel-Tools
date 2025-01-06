<?php

namespace Gleman17\LaravelTools\Services\GenerateRelationship;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class TwoStepRelationshipGenerator extends AbstractRelationshipGenerator
{
    /**
     * Generates a two-step relationship method (hasManyThrough or belongsToThrough).
     *
     * @param string $relationshipName
     * @param class-string $relatedModelClass
     * @param array<array{
     *     table: string,
     *     next_table: string,
     *     column: string,
     *     next_column: string,
     *     local_key: string,
     *     through_local_key: string
     * }> $resolvedPath
     * @param bool $reverse
     * @return string
     */
    public function generate(string $relationshipName, string $relatedModelClass, array $resolvedPath, bool $reverse): string
    {
        $intermediateModelClass = '\\App\\Models\\' . Str::studly(Str::singular($resolvedPath[0]['next_table']));

        $relationshipData = $reverse
            ? $this->generateBelongsToThroughData($relatedModelClass, $intermediateModelClass, $resolvedPath)
            : $this->generateHasManyThroughData($relatedModelClass, $intermediateModelClass, $resolvedPath);

        $formattedArguments = implode(",\n            ", $relationshipData['arguments']);

        $methodContent = <<<EOT
/**
 * {$relationshipData['docBlock']} relationship to $relatedModelClass
 *
 * @return {$relationshipData['shortReturnType']}
 */
public function $relationshipName(): {$relationshipData['shortReturnType']}
{
    return \$this->{$relationshipData['relationshipType']}(
        $formattedArguments
    );
}
EOT;
        return $this->cleanUpIndentation($methodContent);
    }

    private function generateBelongsToThroughData(string $relatedModelClass, string $intermediateModelClass, array $resolvedPath): array
    {
        $foreignKey = $resolvedPath[0]['column'] ?? null;
        $throughKey = $resolvedPath[1]['next_column'] ?? null;
        $localKey = $resolvedPath[0]['local_key'] ?? null;
        $throughLocalKey = $resolvedPath[1]['through_local_key'] ?? null;

        $arguments = [
            $relatedModelClass . '::class',
            $intermediateModelClass . '::class',
        ];

        $lookupArguments = [];

        $lookupArguments['foreignKeyLookup'] = '['.$relatedModelClass.'::class => \''. $foreignKey . '\']';
        $lookupArguments['localKeyLookup'] = '['.$intermediateModelClass.'::class => \''. $localKey . '\']';

        $formattedLookupArguments = [];
        foreach ($lookupArguments as $key => $value) {
            $formattedLookupArguments[] = "$key: $value";
        }


        return [
            'relationshipType' => 'belongsToThrough',
            'docBlock' => 'BelongsToThrough',
            'arguments' => array_merge($arguments, $formattedLookupArguments),
            'returnType' => '\Znck\Eloquent\Relations\BelongsToThrough',
            'shortReturnType' => '\Znck\Eloquent\Relations\BelongsToThrough',
        ];
    }
    /**
     * Generates data for a hasManyThrough relationship.
     *
     * @param class-string $relatedModelClass
     * @param class-string $intermediateModelClass
     * @param array<array{
     *     table: string,
     *     next_table: string,
     *     column: string,
     *     next_column: string,
     *     local_key: string,
     *     through_local_key: string
     * }> $resolvedPath
     * @return array{relationshipType: string, docBlock: string, arguments: array<string>, returnType: class-string<\Illuminate\Database\Eloquent\Relations\HasManyThrough>, shortReturnType: string}
     */
    private function generateHasManyThroughData(string $relatedModelClass, string $intermediateModelClass, array $resolvedPath): array
    {
        $arguments = [
            $relatedModelClass . '::class',
            $intermediateModelClass . '::class',
        ];

        if (isset($resolvedPath[0]['column']) && isset($resolvedPath[1]['column'])) {
            $arguments[] = "'{$resolvedPath[0]['column']}'";
            $arguments[] = "'{$resolvedPath[1]['column']}'";
        } else {
            $currentModelBaseName = Str::studly(Str::singular(class_basename($this)));
            $intermediateModelBaseName = Str::studly(Str::singular(basename(str_replace('\\', '/', $intermediateModelClass))));
            $relatedModelBaseName = Str::studly(Str::singular(class_basename($relatedModelClass)));

            $arguments[] = "'" . Str::snake($intermediateModelBaseName) . "_id'"; // Foreign key on through
            $arguments[] = "'id'"; // Local key on current
            $arguments[] = "'id'"; // Local key on related
            $arguments[] = "'" . Str::snake($relatedModelBaseName) . "_id'"; // Foreign key on related
        }

        return [
            'relationshipType' => 'hasManyThrough',
            'docBlock' => 'HasManyThrough',
            'arguments' => $arguments,
            'returnType' => HasManyThrough::class,
            'shortReturnType' => 'HasManyThrough',
        ];
    }

    /**
     * @param string $methodContent
     * @return string
     */
    public function cleanUpIndentation(string $methodContent): string
    {
        // Split the method content into lines
        $lines = explode("\n", $methodContent);

        // Indent all lines *except* the first one
        for ($i = 1; $i < count($lines); $i++) {
            $lines[$i] = '    ' . $lines[$i];
        }

        return implode("\n", $lines);
    }
}
