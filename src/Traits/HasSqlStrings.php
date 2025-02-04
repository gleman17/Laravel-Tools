<?php

namespace gleman17\laravel_tools\Traits;

trait HasSqlStrings
{
    private string $sqlIndent = '    ';
    private string $lineBreak = "\n";
    private array $baseKeywords = [
        'SELECT', 'FROM', 'WHERE', 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT', 'OFFSET'
    ];

    public function prettyPrintSQL(string $sql): string
    {
        $sql = $this->normalizeWhitespace($sql);

        // Special handling for single column SELECT
        if (preg_match('/^SELECT\s+(\w+)\s+FROM/i', $sql, $matches)) {
            return "SELECT " . $matches[1] . "\nFROM" . substr($sql, stripos($sql, 'FROM') + 4);
        }

        // First normalize all keywords to their uppercase form
        foreach ($this->baseKeywords as $keyword) {
            $sql = preg_replace('/\b' . preg_quote($keyword, '/') . '\b(?![A-Z])/i', strtoupper($keyword), $sql);
        }

        // Now normalize the JOIN keywords
        $sql = preg_replace('/\b(LEFT|RIGHT|INNER|CROSS|FULL)\s+JOIN\b/i', '$1 JOIN', $sql);

        // Split on keywords
        $lines = $this->splitIntoLines($sql);
        return $this->formatLines($lines);
    }

    private function normalizeWhitespace(string $sql): string
    {
        return preg_replace('/\s+/', ' ', trim($sql));
    }

    private function splitIntoLines(string $sql): array
    {
        foreach ($this->baseKeywords as $keyword) {
            $sql = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/', "\n" . $keyword, $sql);
        }

        // Handle JOIN statements - make sure to catch the full JOIN phrase
        $sql = preg_replace('/\b((?:LEFT|RIGHT|INNER|CROSS|FULL)?\s*JOIN)\b/i', "\n$1", $sql);

        return array_filter(array_map('trim', explode("\n", $sql)));
    }

    private function formatLines(array $lines): string
    {
        $formattedLines = [];
        $inSelect = false;

        foreach ($lines as $i => $line) {
            if ($this->startsWithKeyword($line, 'SELECT')) {
                $formattedLines[] = 'SELECT';
                $inSelect = true;

                // Extract and format columns
                $columnsStr = substr($line, 6);
                if ($columnsStr) {
                    $columns = array_map('trim', explode(',', $columnsStr));
                    foreach ($columns as $j => $column) {
                        if (empty(trim($column))) continue;
                        $formattedLines[] = $this->sqlIndent . $column .
                            ($j < count($columns) - 1 ? ',' : '');
                    }
                }
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

            // Handle major clauses that should never be indented
            if ($this->startsWithAnyKeyword($line, ['ORDER BY', 'GROUP BY', 'LIMIT', 'OFFSET'])) {
                $formattedLines[] = $line;
                continue;
            }

            // Handle AND/OR conditions
            if ($this->startsWithAnyKeyword($line, ['AND', 'OR'])) {
                $formattedLines[] = $this->sqlIndent . $this->sqlIndent . $line;
                continue;
            }

            // Handle everything else
            $formattedLines[] = ($inSelect ? $this->sqlIndent : '') . $line;
        }

        return implode($this->lineBreak, $formattedLines);
    }

    private function formatConditions(string $conditions): array
    {
        $formattedConditions = [];
        $parts = preg_split('/(AND|OR)/i', $conditions, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) <= 1) {
            $formattedConditions[] = $this->sqlIndent . $this->sqlIndent . trim($conditions);
            return $formattedConditions;
        }

        $currentLine = $this->sqlIndent . $this->sqlIndent . trim($parts[0]);
        for ($i = 1; $i < count($parts); $i += 2) {
            $operator = trim($parts[$i]);
            $condition = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';

            $formattedConditions[] = $currentLine;
            $currentLine = $this->sqlIndent . $this->sqlIndent . $operator . ' ' . $condition;
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
