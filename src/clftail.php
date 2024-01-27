<?php

function clfTail($searchDict, $page, $linesPerPage)
{
    // Path to the CLF log file
    $logFilePath = '/access.log';
    $escFilePath = escapeshellarg($logFilePath);

    // get search parameters
    $search = $searchDict['search'];
    $ip = $searchDict['ip'];
    $date = $searchDict['date'];
    $stat = $searchDict['stat'];

    // build UNIX command
    if ($searchDict) {
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
        if ($stat) {
            $grepSearch .= " -e $stat";
        }
        $cmd = "grep $grepSearch $escFilePath | tac | head -n $linesPerPage";
    } else {
        // compute the first and last line numbers
        $firstLine = $page * $linesPerPage + 1;
        $lastLine = $firstLine + ($linesPerPage - 1);
        $cmd = "tail -n $lastLine $escFilePath | head -n $linesPerPage | tac";
    }

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

        // If $ip is set, skip this line if it doesn't contain $ip
        if ($ip !== null && strpos($matches[1], $ip) === false) {
            continue;
        }

        // If $date is set, skip this line if it doesn't contain $date
        if ($date !== null && strpos($matches[2], $date) === false) {
            continue;
        }

        // If $stat is set, skip this line if it doesn't contain $stat
        if ($stat !== null && strpos($matches[4], $stat) === false) {
            continue;
        }

        // If $search is set, skip this line if it doesn't contain $search
        if ($search !== null && strpos($line, $search) === false) {
            continue;
        }

        // Go through each match and add to the array with htmlspecialchars()
        $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
    }

    // Output the array as JSON
    echo json_encode($logLines);
}
