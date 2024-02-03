<?php

function clfTail($page, $linesPerPage)
{
    // Path to the CLF log file
    $logFilePath = '/access.log';
    $escFilePath = escapeshellarg($logFilePath);

    // build UNIX command
    $firstLine = $page * $linesPerPage + 1;
    $lastLine = $firstLine + ($linesPerPage - 1);
    $cmd = "tail -n $lastLine $escFilePath | head -n $linesPerPage | tac";

    // execute UNIX command and read lines from pipe
    $fp = popen($cmd, 'r');
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

        // Go through each match and add to the array with htmlspecialchars()
        $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
    }

    // Output the array as JSON
    echo json_encode($logLines);
}
