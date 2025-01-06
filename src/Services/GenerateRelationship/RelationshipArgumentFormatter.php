<?php

namespace Gleman17\LaravelTools\Services\GenerateRelationship;

class RelationshipArgumentFormatter
{
    /**
     * @param array<string|array<string, string>> $arguments
     * @return string
     */
    public static function format(array $arguments): string
    {
        return implode(",\n            ", array_map(function ($argument) {
            $comment = self::getComment($argument);
            return trim($argument) . ", " . $comment;
        }, $arguments));
    }

    /**
     * @param string|array<string, string> $argument
     * @return string
     */
    private static function getComment(array|string $argument): string
    {
        if (str_contains($argument, '::class')) {
            return '// Related Model Class';
        } elseif (str_contains($argument, "'")) {
            return '// Foreign/Local Key';
        } else {
            return '// Other Argument';
        }
    }
}
