<?php

namespace gleman17\laravel_tools\Traits;

use Illuminate\Support\Facades\Log;

trait HasEloquentStrings
{
    public function prettyPrintEloquent(string $input): string
    {
        info('Starting pretty print', ['input' => $input]);

        if (empty($input)) {
            info('Empty input detected');
            return '';
        }

        $indent = str_repeat(' ', 4);

        // If no method chaining, return as is
        if (!str_contains($input, '->')) {
            info('No method chaining detected');
            return $input;
        }

        // Split into base and method chains
        $parts = explode('->', $input);
        info('Initial split:', ['parts' => $parts]);

        $base = array_shift($parts);
        $result = $base;

        // Process each method chain
        $currentPart = '';
        $openBraces = 0;

        foreach ($parts as $i => $part) {
            info('Processing part ' . $i, ['part' => $part]);

            // Count braces in this part
            $braces = substr_count($part, '{') - substr_count($part, '}');
            $openBraces += $braces;
            info('Brace count', ['current' => $braces, 'total' => $openBraces]);

            if ($openBraces > 0 || strpos($part, 'function') !== false) {
                // We're inside a closure or starting one
                $currentPart = $currentPart ? $currentPart . '->' . $part : $part;
                info('Building closure part', ['currentPart' => $currentPart]);
            } else if ($openBraces === 0 && $currentPart) {
                // We've completed a closure
                $fullPart = $currentPart . ($currentPart ? '->' : '') . $part;
                info('Processing complete closure', ['fullPart' => $fullPart]);

                // Format the closure body
                $pattern = '/^(.*?function\s*\(\s*\$\w+\s*\)\s*{\s*)(.*?)\s*}(.*)$/s';
                if (preg_match($pattern, $fullPart, $matches)) {
                    info('Closure matches', ['matches' => $matches]);

                    $methodAndFunc = $matches[1];  // everything up to and including {
                    $body = $matches[2];           // closure body
                    $remainder = $matches[3];      // anything after }

                    // Split body into statements and remove empty ones
                    $statements = array_values(array_filter(array_map('trim', explode(';', trim($body)))));

                    // Format statements
                    $formattedStatements = [];
                    foreach ($statements as $statement) {
                        // Split by arrow operator but preserve the first two parts together
                        $chainParts = array_map('trim', explode('->', $statement));

                        if (count($chainParts) > 2) {
                            // Keep first two parts together ($query->firstMethod)
                            $firstParts = array_slice($chainParts, 0, 2);
                            $restParts = array_slice($chainParts, 2);

                            $formatted = $indent . $indent . $firstParts[0] . '->' . $firstParts[1];

                            // Add remaining parts with proper indentation
                            foreach ($restParts as $part) {
                                $formatted .= "\n" . $indent . $indent . '->' . $part;
                            }

                            $formattedStatements[] = $formatted;
                        } else {
                            // Simple statement or single chain
                            $formattedStatements[] = $indent . $indent . $statement;
                        }
                    }

                    $result .= "\n" . $indent . '->' . trim(rtrim($methodAndFunc, "{ ")) . " {";
                    if (!empty($formattedStatements)) {
                        $result .= "\n" . implode(";\n", $formattedStatements) . ";";
                    }
                    $result .= "\n" . $indent . '}' . $remainder;
                } else {
                    $result .= "\n" . $indent . '->' . trim($fullPart);
                }

                $currentPart = '';
            } else {
                // Regular method chain
                $result .= "\n" . $indent . '->' . trim($part);
            }
        }

        info('Final formatted result', ['result' => $result]);
        return $result;
    }
}
