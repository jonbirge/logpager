<?php

$uniqueId = $_GET['id'] ?? '';
$tempFile = "/tmp/scan_output_" . $uniqueId . ".txt";
$lockFile = "/tmp/scan_output_" . $uniqueId . ".lock";

// Open the file for reading
$handle = fopen($tempFile, "r");

// Check if the file is opened successfully
if ($handle) {
    $scanLines = [];

    // Read the file line by line
    while (($line = fgets($handle)) !== false) {
        $scanLines[] = $line;
    }

    // Close the file
    fclose($handle);

    // Check for the existence of the lock file
    if (!file_exists($lockFile)) {
        // add a EOF to the end of the array to indicate that the scan is done
        $scanLines[] = "EOF";
    }

    // Convert the line array to JSON format and output it, making sure to escape any special characters
    echo json_encode($scanLines, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
} else {
    // Error handling in case the file cannot be opened
    echo "Error: Unable to open the file.";
}
