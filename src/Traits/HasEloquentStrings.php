<?php

namespace gleman17\laravel_tools\Traits;

use Illuminate\Support\Facades\Log;

trait HasEloquentStrings
{
    private string $eloquentIndent = '    '; // Four spaces
    private string $closurePattern = '/^(.*?function\s*\(\s*\$\w+\s*\)\s*{\s*)(.*?)\s*}(.*)$/s';
    private string $lineBreak = "\n";
    private string $methodArrow = '->';
    private string $leftBrace = '{';
    private string $rightBrace = '}';
    private string $semicolon = ';';
    private string $queryVariable = '$query';

    // Named capture groups for more readable pattern matches
    private array $groupNames = [
        'methodAndFunc' => 1,  // everything up to and including {
        'body' => 2,           // closure body
        'remainder' => 3       // anything after }
    ];

    private array $returnParts = [
        'parts' => 0,          // method chain parts
        'result' => 1,         // initial result
        'currentPart' => 2,    // current part accumulator
        'openBraces' => 3      // open braces counter
    ];

    public function prettyPrintEloquent(string $input): string
    {
        if (empty($input)) {
            return '';
        }

        if (!str_contains($input, $this->methodArrow)) {
            return $input;
        }

        [$parts, $result, $currentPart, $openBraces] = $this->setupMethodChaining($input);

        foreach ($parts as $i => $part) {
            [$result, $currentPart, $openBraces] = $this->processMethodChainPart(
                $part, $result, $currentPart, $openBraces
            );
        }

        return $result;
    }

    private function processMethodChainPart(string $part, string $result, string $currentPart, int $openBraces): array
    {
        $braces = substr_count($part, $this->leftBrace) - substr_count($part, $this->rightBrace);
        $openBraces += $braces;

        // Handle closure accumulation
        if ($openBraces > 0 || str_contains($part, 'function')) {
            $currentPart = $currentPart ? $currentPart . $this->methodArrow . $part : $part;
            return [$result, $currentPart, $openBraces];
        }

        // Handle completed closure
        if ($currentPart !== '') {
            $result = $this->processCompletedClosure($result, $currentPart, $part);
            return [$result, '', $openBraces];
        }

        // Handle regular method chain
        $result .= $this->formatMethodChain(trim($part));
        return [$result, $currentPart, $openBraces];
    }

    private function formatMethodChain(string $part): string
    {
        return $this->lineBreak . $this->eloquentIndent . $this->methodArrow . $part;
    }

    private function processCompletedClosure(string $result, string $currentPart, string $part): string
    {
        $fullPart = $currentPart . ($currentPart ? $this->methodArrow : '') . $part;

        if (!$this->isClosurePattern($fullPart)) {
            return $result . $this->formatMethodChain(trim($fullPart));
        }

        return $this->formatClosurePart($result, $fullPart);
    }

    private function isClosurePattern(string $part): bool
    {
        return preg_match($this->closurePattern, $part) === 1;
    }

    private function formatClosurePart(string $result, string $fullPart): string
    {
        preg_match($this->closurePattern, $fullPart, $matches);

        $methodAndFunc = $matches[$this->groupNames['methodAndFunc']];
        $body = $matches[$this->groupNames['body']];
        $remainder = $matches[$this->groupNames['remainder']];

        // Format the closure structure
        $result .= $this->formatMethodChain(trim(rtrim($methodAndFunc, "{ "))) . " {";

        // Add formatted statements if body is not empty
        $statements = $this->parseClosureStatements($body);
        if (!empty($statements)) {
            $result .= $this->lineBreak . implode($this->semicolon . $this->lineBreak, $statements) . $this->semicolon;
        }

        $result .= $this->lineBreak . $this->eloquentIndent . $this->rightBrace . $remainder;

        return $result;
    }

    private function parseClosureStatements(string $body): array
    {
        $statements = array_values(array_filter(array_map(
            'trim',
            explode($this->semicolon, trim($body))
        )));

        return array_reduce($statements, function ($formattedStatements, $statement) {
            return $this->formatStatements($statement, $formattedStatements);
        }, []);
    }

    public function formatStatements(mixed $statement, array $formattedStatements): array
    {
        $chainParts = array_map('trim', explode($this->methodArrow, $statement));

        if (count($chainParts) <= 2) {
            $formattedStatements[] = $this->eloquentIndent . $this->eloquentIndent . $statement;
            return $formattedStatements;
        }

        $formatted = $this->formatChainedStatement($chainParts);
        $formattedStatements[] = $formatted;

        return $formattedStatements;
    }

    private function formatChainedStatement(array $chainParts): string
    {
        $firstParts = array_slice($chainParts, 0, 2);
        $restParts = array_slice($chainParts, 2);

        $formatted = $this->eloquentIndent . $this->eloquentIndent
            . $firstParts[0] . $this->methodArrow . $firstParts[1];

        foreach ($restParts as $part) {
            $formatted .= $this->lineBreak . $this->eloquentIndent . $this->eloquentIndent
                . $this->methodArrow . $part;
        }

        return $formatted;
    }

    public function setupMethodChaining(string $input): array
    {
        $parts = explode($this->methodArrow, $input);
        $base = array_shift($parts);

        return [
            $parts,                             // method chain parts
            $base,                              // initial result
            '',                                 // current part accumulator
            0                                   // open braces counter
        ];
    }
}
