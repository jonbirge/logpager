<?php

// Path to the CLF log file
$logFilePath = '/access.log';

// Get parameters from URL
$page = $_GET['page'] ?? 0;
$linesPerPage = $_GET['n'] ?? 20;

// IP addresses to exclude from counts
include 'exclude.php';
$excludedIPs = getExcludedIPs();

// compute the first and last line numbers
$firstLine = $page * $linesPerPage + 1;
$lastLine = $firstLine + ($linesPerPage - 1);

// generate UNIX grep command line arguments to exclude IP addresses
$escFilePath = escapeshellarg($logFilePath);
$grepArgs = '';
foreach ($excludedIPs as $ip) {
    $grepArgs .= " -e $ip";
}
$grepCmd = "grep -v $grepArgs $escFilePath";

// build UNIX command to get the last $linesPerPage lines
$cmd = "$grepCmd | tail -n $lastLine | head -n $linesPerPage";

// execute UNIX command
$fp = popen($cmd, 'r');

// read the lines from UNIX pipe
$lines = [];
while ($line = fgets($fp)) {
    $lines[] = $line;
}

// Read in CLF header name array from clfhead.json
$headers = json_decode(file_get_contents('clfhead.json'));

// Create array of CLF log lines
$logLines = [];
$logLines[] = $headers;

// Process each line and add to the array
foreach ($lines as $line) {
    preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) (\S+)/', $line, $matches);
    // Go through each match and add to the array with htmlspecialchars()
    $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
}

// Output the array as JSON
echo json_encode($logLines);

?>
