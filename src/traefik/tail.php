<?php

// Return list of auth log files
function getTraefikLogFiles()
{
    // Array of log files to read
    $logFilePaths = ['/access.log.1', '/access.log'];

    // Remove any log files that don't exist
    foreach ($logFilePaths as $key => $logFilePath) {
        if (!file_exists($logFilePath)) {
            unset($logFilePaths[$key]);
        }
    }

    return $logFilePaths;
}

function readAllLinesFromFiles($logFilePaths)
{
    $allLines = [];
    foreach ($logFilePaths as $filePath) {
        $file = fopen($filePath, 'r');
        while (!feof($file)) {
            $line = fgets($file);
            if ($line !== false) {
                $allLines[] = $line;
            }
        }
        fclose($file);
    }
    return $allLines;
}

function tail($page, $linesPerPage)
{
    // Retrieve log file paths using getTraefikLogFiles()
    $logFilePaths = getTraefikLogFiles();

    // Read all lines from log files
    $allLines = readAllLinesFromFiles($logFilePaths);

    // Calculate total lines and number of pages
    $totalLines = count($allLines);
    $pageCount = ceil($totalLines / $linesPerPage);

    // Calculate the page number we'll actually be returning
    $page = min($page, $pageCount - 1);

    // Determine the range of lines to read
    $startLine = max(0, $totalLines - ($page + 1) * $linesPerPage);
    $endLine = min($totalLines, $totalLines - $page * $linesPerPage);

    // Extract the lines for the requested page
    $lines = array_slice($allLines, $startLine, $endLine - $startLine);

    // Read in CLF header name array from loghead.json
    $headers = json_decode(file_get_contents('traefik/loghead.json'));

    // Create array of CLF log lines
    $logLines = [];
    $logLines[] = $headers;

    // Process each line and add to the array
    foreach ($lines as $line) {
        // Extract the important CLF and Traefik special fields from the line
        preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) \S+ \"-\" \"-\" \S+ \"(\S+)\" \"\S+\" \S+/', $line, $matches);

        // swap the last two matches so the status is always last
        $temp = $matches[4];
        $matches[4] = $matches[5];
        $matches[5] = $temp;

        // Go through each match and add to the array with htmlspecialchars()
        $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
    }

    // Output $logLines, $page and $lineCount as a JSON dictionary
    echo json_encode([
        'page' => $page,
        'pageCount' => $pageCount,
        'lineCount' => $totalLines,
        'logLines' => $logLines
    ]);
}