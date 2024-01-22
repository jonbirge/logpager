<?php

// IP addresses to exclude from counts
include 'searchparse.php';
include 'exclude.php';
$excludedIPs = getExcludedIPs();

// Path to the CLF log file
$logFilePath = '/access.log';

// Get parameters from URL
$search = $_GET['search'] ?? null;
$page = $_GET['page'] ?? 0;  // ignored for search
$linesPerPage = $_GET['n'] ?? 16;

if ($search) {
    $doSearch = true;
} else {
    $doSearch = false;
}
[$search, $ip, $date] = parseSearch($search);

// generate UNIX grep command line arguments to exclude IP addresses
$escFilePath = escapeshellarg($logFilePath);
$grepArgs = '';
foreach ($excludedIPs as $exip) {
    $grepArgs .= " -e $exip";
}
$ipCmd = "grep -v $grepArgs $escFilePath";

// build UNIX command
if ($doSearch) {
    // build grep search command from $search, $ip, and $date
    $grepSearch = '';
    if ($search) {
        $grepSearch .= " -e $search";
    }
    if ($ip) {
        $grepSearch .= " -e $ip";
    }
    if ($date) {
        $grepSearch .= " -e $date";
    }
    $cmd = "$ipCmd | grep $grepSearch | tac | head -n $linesPerPage";
} else {
    // compute the first and last line numbers
    $firstLine = $page * $linesPerPage + 1;
    $lastLine = $firstLine + ($linesPerPage - 1);
    $cmd = "$ipCmd | tail -n $lastLine | head -n $linesPerPage | tac";
}

// execute UNIX command
$fp = popen($cmd, 'r');

// read the lines from UNIX pipe
$lines = [];
while ($line = fgets($fp)) {
    $lines[] = $line;
}

pclose($fp);

// Read in CLF header name array from clfhead.json
$headers = json_decode(file_get_contents('loghead.json'));

// Create array of CLF log lines
$logLines = [];
$logLines[] = $headers;

// Process each line and add to the array
foreach ($lines as $line) {
    // Extract the CLF fields from the line
    preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+)/', $line, $matches);

    // If $ip is set, skip this line if it doesn't contain $ip
    if ($ip !== null && strpos($matches[1], $ip) === false) {
        continue;
    }

    // If $date is set, skip this line if it doesn't contain $date
    if ($date !== null && strpos($matches[2], $date) === false) {
        continue;
    }
    
    // Go through each match and add to the array with htmlspecialchars()
    $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
}

// Output the array as JSON
echo json_encode($logLines);
