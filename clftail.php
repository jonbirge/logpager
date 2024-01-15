<?php

// IP addresses to exclude from counts
include 'exclude.php';
$excludedIPs = getExcludedIPs();

// Path to the CLF log file
$logFilePath = '/access.log';

// Get parameters from URL
$search = $_GET['search'] ?? null;
$page = $_GET['page'] ?? 0;  // ignored for search
$linesPerPage = $_GET['n'] ?? 16;

// generate UNIX grep command line arguments to exclude IP addresses
$escFilePath = escapeshellarg($logFilePath);
$grepArgs = '';
foreach ($excludedIPs as $ip) {
    $grepArgs .= " -e $ip";
}
$grepCmd = "grep -v $grepArgs $escFilePath";

// build UNIX command
if (!$search) {
    // compute the first and last line numbers
    $firstLine = $page * $linesPerPage + 1;
    $lastLine = $firstLine + ($linesPerPage - 1);
    $cmd = "$grepCmd | tail -n $lastLine | head -n $linesPerPage | tac";
} else {
    $escSearch = escapeshellarg($search);
    $srchCmd .= "grep $escSearch";
    $cmd = "$grepCmd | tail -n $linesPerPage | tac";
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

// Check $search string for terms preceded by ip: or date:, and assume there is,
// at most, one of each. Remove the terms from $string and set $ip and $date to
// the values. If $string is empty afterwards, set it to null.
$ip = null;
$date = null;
if ($search) {
    $search = trim($search);
    $ipPos = strpos($search, 'ip:');
    $datePos = strpos($search, 'date:');
    if ($ipPos !== false) {
        $ip = substr($search, $ipPos + 3);
        $search = trim(substr($search, 0, $ipPos));
    }
    if ($datePos !== false) {
        $date = substr($search, $datePos + 5);
        $search = trim(substr($search, 0, $datePos));
    }
    if ($search === '') {
        $search = null;
    }
}

// Process each line and add to the array
foreach ($lines as $line) {
    // Extract the CLF fields from the line
    preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+)/', $line, $matches);

    // If $search is set, skip this line if it doesn't contain $search
    if ($search !== null && strpos($matches[2], $search) === false) {
        continue;
    }

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
