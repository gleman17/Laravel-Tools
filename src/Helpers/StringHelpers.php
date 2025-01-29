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
