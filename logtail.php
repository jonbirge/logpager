<?php

// Get parameters from URL
$page = $_GET['page'] ?? 0;
$linesPerPage = $_GET['n'] ?? 20;

// Path to the CLF log file
$logFilePath = '/access.log';

// Function to read the nth page from the end of the file
function getTailPage($filePath, $linesPerPage, $page = 0) {
    // compute the first and last line numbers
    $firstLine = $page * $linesPerPage + 1;
    $lastLine = $firstLine + ($linesPerPage - 1);

    // use popen to read the file in reverse using fast unix tools
    $cmd = sprintf('tail -n %d %s | head -n %d', $lastLine, escapeshellarg($filePath), $linesPerPage);
    $fp = popen($cmd, 'r');

    // read the lines from the pipe
    $lines = [];
    while ($line = fgets($fp)) {
        $lines[] = $line;
    }

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
