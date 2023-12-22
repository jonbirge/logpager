<?php

// Parameters
$maxResults = 100;
$logFile = "/access.log";
$searchLines = 10000;

// Get search term from URL
$searchTerm = $_GET['term'];

// Read in up to 1000 lines from the log file, starting at the end
$lines = array();
$handle = fopen($logFile, "r");
if ($handle) {
    $lineCount = 0;
    $pos = -2;
    $beginning = false;
    while ($lineCount < $searchLines) {
        $t = " ";
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true; 
                break; 
            }
            $t = fgetc($handle);
            $pos--;
        }
        $lineCount++;
        if ($beginning) {
            rewind($handle);
        }
        $lines[$lineCount] = fgets($handle);
        if ($beginning) break;
    }
    fclose ($handle);
}

// Loop through the lines and return the first $maxResults lines that contain
// the search term
$results = array();
foreach ($lines as $line) {
    if (strpos($line, $searchTerm) !== false) {
        $results[] = $line;
        if (count($results) >= $maxResults) {
            break;
        }
    }
}

// Make array of CLF log headers: IP Address, Timestamp, Request, Status, Size
$headers = ['IP Address', 'Timestamp', 'Request', 'Status', 'Size'];

// Create array of CLF log lines
$logLines = [];
$logLines[] = $headers;

// Process each line and add to the array
foreach ($results as $line) {
    preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) (\S+)/', $line, $matches);
    // Go through each match and add to the array with htmlspecialchars()
    $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
}

// Return JSON encoded array
echo json_encode($logLines);

?>
