<?php

// Get search term from URL
$searchTerm = $_GET['term'] ?? '';
if ($searchTerm == '') {
    echo json_encode([]);
}

// Get the list of excluded IPs
include 'exclude.php';
$excludedIPs = getExcludedIPs();

// Parameters
$maxResults = 1024;
$logFile = "/access.log";

// Build UNIX tool command
$sedCmd = '';
foreach ($excludedIPs as $ip) {
    $sedCmd .= "sed '/$ip/d' | ";
}
$command = "grep '$searchTerm' $logFile | " . $sedCmd . "tail -n $maxResults";

// Run command and store results in array
exec($command, $results);

// Read in CLF header name array from clfhead.json
$headers = json_decode(file_get_contents('clfhead.json'));

// Create array of CLF log lines
$logLines = [];
$logLines[] = $headers;

// Process each line and add to the array
foreach ($results as $line) {
    preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) (\S+)/', $line, $matches);
    // Skip this log entry if the IP address is in the excluded list
    if (!in_array($matches[1], $excludedIPs)) {
        $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
    }
}

// Return JSON encoded array
echo json_encode($logLines);

?>
