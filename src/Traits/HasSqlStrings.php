<?php

namespace Gleman17\LaravelTools\Traits;

trait HasSqlStrings
{
    private string $sqlIndent = '    ';
    private string $lineBreak = "\n";
    private array $baseKeywords = [
        'SELECT', 'FROM', 'WHERE', 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT', 'OFFSET'
    ];
    private array $joinTypes = ['LEFT', 'RIGHT', 'INNER', 'CROSS', 'FULL'];

    private string $singleColumnSelectPattern = '/^SELECT\s+(\w+)\s+FROM/i';
    private string $extraWhitespacePattern = '/\s+/';
    private string $joinPattern = '/\b((?:LEFT|RIGHT|INNER|CROSS|FULL)?\s*JOIN)\b/i';

    private array $unindentedClauses = ['ORDER BY', 'GROUP BY', 'LIMIT', 'OFFSET'];
    private array $indentedOperators = ['AND', 'OR'];

    private int $doubleIndentSpaces = 8;

    public function prettyPrintSQL(string $sql): string
    {
        $sql = $this->normalizeWhitespace($sql);

        if (preg_match($this->singleColumnSelectPattern, $sql, $matches)) {
            return "SELECT " . $matches[1] . "\nFROM" . substr($sql, stripos($sql, 'FROM') + 4);
        }

        foreach ($this->baseKeywords as $keyword) {
            $sql = $this->normalizeKeyword($keyword, $sql);
        }

        $sql = preg_replace($this->joinPattern, '$1', $sql);

        $lines = $this->splitIntoLines($sql);
        return $this->formatLines($lines);
    }

    private function normalizeWhitespace(string $sql): string
    {
        return preg_replace($this->extraWhitespacePattern, ' ', trim($sql));
    }

    private function normalizeKeyword(string $keyword, string $sql): string
    {
        return preg_replace('/\b' . preg_quote($keyword, '/') . '\b(?![A-Z])/i', strtoupper($keyword), $sql);
    }

    private function splitIntoLines(string $sql): array
    {
        foreach ($this->baseKeywords as $keyword) {
            $sql = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/', "\n" . $keyword, $sql);
        }

        $sql = preg_replace($this->joinPattern, "\n$1", $sql);

        return array_filter(array_map('trim', explode("\n", $sql)));
    }

    private function formatLines(array $lines): string
    {
        $formattedLines = [];
        $inSelect = false;

        foreach ($lines as $line) {
            if ($this->startsWithKeyword($line, 'SELECT')) {
                $formattedLines = array_merge(
                    $formattedLines,
                    $this->formatSelectClause($line)
                );
                $inSelect = true;
                continue;
            }

            if ($this->startsWithKeyword($line, 'FROM')) {
                $inSelect = false;
                $formattedLines[] = 'FROM' . substr($line, 4);
                continue;
            }

            if ($this->startsWithKeyword($line, 'WHERE')) {
                $formattedLines[] = 'WHERE';
                $conditions = substr($line, 5);
                $formattedLines = array_merge(
                    $formattedLines,
                    $this->formatConditions($conditions)
                );
                continue;
            }

            if ($this->startsWithAnyKeyword($line, $this->unindentedClauses)) {
                $formattedLines[] = $line;
                continue;
            }

            if ($this->startsWithAnyKeyword($line, $this->indentedOperators)) {
                $formattedLines[] = str_repeat($this->sqlIndent, 2) . $line;
                continue;
            }

            $formattedLines[] = ($inSelect ? $this->sqlIndent : '') . $line;
        }

        return implode($this->lineBreak, $formattedLines);
    }

    private function formatSelectClause(string $line): array
    {
        $formattedLines = ['SELECT'];
        $columnsStr = substr($line, 6);

        if ($columnsStr) {
            $columns = array_map('trim', explode(',', $columnsStr));
            foreach ($columns as $j => $column) {
                if (empty(trim($column))) continue;
                $formattedLines[] = $this->sqlIndent . $column .
                    ($j < count($columns) - 1 ? ',' : '');
            }
        }

        return $formattedLines;
    }

    private function formatConditions(string $conditions): array
    {
        $formattedConditions = [];
        $parts = preg_split('/(AND|OR)/i', $conditions, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) <= 1) {
            $formattedConditions[] = str_repeat($this->sqlIndent, 2) . trim($conditions);
            return $formattedConditions;
        }

        $currentLine = str_repeat($this->sqlIndent, 2) . trim($parts[0]);
        for ($i = 1; $i < count($parts); $i += 2) {
            $operator = trim($parts[$i]);
            $condition = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';

            $formattedConditions[] = $currentLine;
            $currentLine = str_repeat($this->sqlIndent, 2) . $operator . ' ' . $condition;
        }

        if ($currentLine) {
            $formattedConditions[] = $currentLine;
        }

        return $formattedConditions;
    }

    private function startsWithKeyword(string $line, string $keyword): bool
    {
        return stripos($line, $keyword) === 0;
    }

    private function startsWithAnyKeyword(string $line, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($this->startsWithKeyword($line, $keyword)) {
                return true;
            }
        }
        return false;
    }
}
