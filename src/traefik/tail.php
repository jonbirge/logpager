<?php

// Include traefik.php
include 'traefik.php';

function tail($page, $linesPerPage)
{
    // Concatenate log files
    $tmpFilePath = getTempLogFilePath();

    // use UNIX wc command to count lines in file
    $cmd = "wc -l $tmpFilePath";
    $fp = popen($cmd, 'r');
    $lineCount = intval(fgets($fp));
    pclose($fp);

    // calculate number of pages
    $pageCount = ceil($lineCount / $linesPerPage);

    // calculate the page number we'll actually be returning
    $page = min($page, $pageCount);

    // build UNIX command
    $lastLine = ($page + 1) * $linesPerPage;  // counting back from end
    $cmd = "tail -n $lastLine $tmpFilePath | head -n $linesPerPage | tac";  // faster near the end of the file

    // execute UNIX command and read lines from pipe
    $fp = popen($cmd, 'r');
    $lines = [];
    while ($line = fgets($fp)) {
        $lines[] = $line;
    }
    pclose($fp);

    // delete temp file
    unlink($tmpFilePath);

    // Read in CLF header name array from loghead.json
    $headers = json_decode(file_get_contents('traefik/loghead.json'));

    // Create array of CLF log lines
    $logLines = [];
    $logLines[] = $headers;

    // Process each line and add to the array
    foreach ($lines as $line) {
        // Extract the important CLF and Traefik special fields from the line
        preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) \S+ \"-\" \"-\" \S+ \"(\S+)\" \"\S+\" \S+/', $line, $matches);

        // swap the last two matches so the status is always last
        $temp = $matches[4];
        $matches[4] = $matches[5];
        $matches[5] = $temp;

        // Go through each match and add to the array with htmlspecialchars()
        $logLines[] = array_map('htmlspecialchars', array_slice($matches, 1));
    }

    // Output $logLines, $page and $lineCount as a JSON dictionary
    echo json_encode([
        'page' => $page,
        'pageCount' => $pageCount,
        'lineCount' => $lineCount,
        'logLines' => $logLines
    ]);
}
