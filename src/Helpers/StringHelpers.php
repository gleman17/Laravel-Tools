<?php
function cleanString($input) {
    // Remove all newlines, carriage returns, and tabs
    $input = preg_replace('/[\r\n\t]/', ' ', $input);

    // Replace multiple spaces with a single space
    $input = preg_replace('/\s+/', ' ', $input);

    // Trim leading and trailing spaces
    $input = trim($input);

    return $input;
}

function prettyPrintSQL(string $sql): string
{
    // Remove extra whitespace
    $sql = preg_replace('/\s+/', ' ', trim($sql));

    // Major SQL keywords that should start a new line
    $keywords = [
        'SELECT',
        'FROM',
        'WHERE',
        'JOIN',
        'LEFT JOIN',
        'RIGHT JOIN',
        'INNER JOIN',
        'GROUP BY',
        'HAVING',
        'ORDER BY',
        'LIMIT',
        'OFFSET'
    ];

    // Add newlines before keywords
    foreach ($keywords as $keyword) {
        $sql = preg_replace('/\b' . $keyword . '\b/i', "\n" . $keyword, $sql);
    }

    // Handle special cases for readability
    $lines = explode("\n", $sql);
    $indentLevel = 0;
    $formattedLines = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Determine if this line should be indented
        if (stripos($line, 'SELECT') === 0) {
            $indentLevel = 1;
        }

        // Add appropriate indentation
        $indent = str_repeat('    ', $indentLevel);

        // Format lists of columns
        if (stripos($line, 'SELECT') === 0) {
            // Split the columns
            $selectParts = explode(',', preg_replace('/^SELECT\s+/', '', $line));
            if (count($selectParts) > 1) {
                $formattedColumns = [];
                foreach ($selectParts as $column) {
                    $formattedColumns[] = $indent . '    ' . trim($column);
                }
                $formattedLines[] = 'SELECT';
                $formattedLines = array_merge($formattedLines, $formattedColumns);
                continue;
            }
        }

        // Add the formatted line
        $formattedLines[] = $indent . $line;

        // Handle AND/OR conditions in WHERE clause
        if (stripos($line, 'WHERE') === 0) {
            $indentLevel = 2;
        } elseif (stripos($line, 'AND') === 0 || stripos($line, 'OR') === 0) {
            $formattedLines[count($formattedLines) - 1] = $indent . '    ' . $line;
        }
    }

    return implode("\n", $formattedLines);
}
