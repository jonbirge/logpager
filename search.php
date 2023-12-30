<?php

// Parameters
$maxResults = 1024;
$logFilePath = "/access.log";

// Get search term from URL
$searchTerm = $_GET['term'] ?? '';
if ($searchTerm == '') {
    echo json_encode([]);
    exit;
}

// Get the list of excluded IPs
include 'exclude.php';
$excludedIPs = getExcludedIPs();

// generate grep command for main search
$escFilePath = escapeshellarg($logFilePath);
$grepInclude = "grep '$searchTerm' $escFilePath";

// generate UNIX grep command line arguments to exclude IP addresses
$grepArgs = '';
foreach ($excludedIPs as $ip) {
    $grepArgs .= " -e $ip";
}
$grepExclude = "grep -v $grepArgs";

// build UNIX command to perform search with exclusions
$cmd = "$grepInclude | $grepExclude | tail -n $maxResults";
echo "<p>$cmd</p>";

// Run command and store results in array
exec($cmd, $results);

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
