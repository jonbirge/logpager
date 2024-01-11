<?php

// Parameters
$maxResults = 1024;
$logFilePath = "/auth.log";

// Get search term from URL
$searchTerm = $_GET['term'] ?? '';
if ($searchTerm == '') {
    echo json_encode([]);
    exit;
}

// generate grep command for main search
$escFilePath = escapeshellarg($logFilePath);
$grepInclude = "grep '$searchTerm' $escFilePath";

// build UNIX command to perform search with exclusions
$cmd = "$grepInclude | tail -n $maxResults";

// Run command and store results in array
exec($cmd, $results);

// Read in CLF header name array from clfhead.json
$headers = json_decode(file_get_contents('clfhead.json'));

// Create array of CLF log lines
$logLines = [];
$logLines[] = $headers;

// Process each line and add to the array
foreach ($results as $line) {
    $logLines[] = explode(' ', $line);
}

// Return JSON encoded array
echo json_encode($logLines);

?>
