<?php

$uniqueId = $_GET['id'] ?? '';
$tempFile = "/tmp/ping_output_" . $uniqueId . ".txt";
$lockFile = "/tmp/ping_output_" . $uniqueId . ".lock";

// Open the file for reading
$handle = fopen($tempFile, "r");

// Check if the file is opened successfully
if ($handle) {
    $pingTimes = [];

    // Read the file line by line
    while (($line = fgets($handle)) !== false) {
        // Search for the pattern 'time=X ms' in each line
        if (preg_match('/time=([\d\.]+) ms/', $line, $matches)) {
            // Add the extracted time (X) to the array
            $pingTimes[] = floatval($matches[1]);
        }
    }

    // Close the file
    fclose($handle);

    // Check for the existence of the lock file
    if (!file_exists($lockFile)) {
        // add a -1 to the end of the array to indicate that the ping is done
        $pingTimes[] = -1;
    }

    // Convert the array to JSON format and output it
    echo json_encode($pingTimes);
} else {
    // Error handling in case the file cannot be opened
    echo "Error: Unable to open the file.";
}
