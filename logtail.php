<?php

// Get parameters from URL
$page = $_GET['page'] ?? 0;
$linesPerPage = $_GET['n'] ?? 20;

// Path to the CLF log file
$logFilePath = '/access.log';

// Function to read the nth page from the end of the file
function getTailPage($filePath, $linesPerPage, $page = 0) {
    // Check if the file exists and is readable
    if (!is_readable($filePath)) {
        return "File not found or not readable.";
    }

    // Open the file for reading
    $fileHandle = fopen($filePath, 'r');
    if (!$fileHandle) {
        return "Unable to open file.";
    }

    // Seek to the end of the file
    fseek($fileHandle, 0, SEEK_END);
    $fileSize = ftell($fileHandle);
    $lines = [];
    $currentLine = '';
    $lineCount = 0;

    // Read backwards to find the starting line
    for ($pos = $fileSize - 2; $pos >= 0; $pos--) {
        fseek($fileHandle, $pos);
        $char = fgetc($fileHandle);

        if ($char === "\n") {
            // Start capturing lines after reaching the required page
            if (++$lineCount >= $linesPerPage * ($page + 1)) {
                break;
            }
            if ($lineCount > $linesPerPage * $page) {
                array_unshift($lines, $currentLine);
                $currentLine = '';
            }
        } elseif ($pos > 0) {
            $currentLine = $char . $currentLine;
        }
    }

    // Add the last line if the file does not end with a newline
    if ($lineCount > $linesPerPage * $page && $currentLine !== '') {
        array_unshift($lines, $currentLine);
    }

    fclose($fileHandle);
    return $lines;
}

// Read the last n lines from the file
$lastPageLines = getTailPage($logFilePath, $linesPerPage, $page);

// Make array of CLF log headers: IP Address, Timestamp, Request, Status, Size
$headers = ['IP Address', 'Timestamp', 'Request', 'Status', 'Size'];

// Create array of CLF log lines
$logLines = [];
$logLines[] = $headers;

// Process each line and add to the array
foreach ($lastPageLines as $line) {
    preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) (\S+)/', $line, $matches);
    // Go through each match and add to the array with htmlspecialchars()
    $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
}

// Output the array as JSON
echo json_encode($logLines);

?>
