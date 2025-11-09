<?php

// Include the clfparse.php file
include 'clfparse.php';

function tail($page, $linesPerPage)
{
    // Get the concatenated log file path
    $tmpFilePath = getCLFTempLogFilePath();
    $escFilePath = escapeshellarg($tmpFilePath);

    // use UNIX wc command to count lines in file
    $cmd = "wc -l $escFilePath";
    $fp = popen($cmd, 'r');
    $lineCount = intval(fgets($fp));
    pclose($fp);

    // calculate number of pages
    $pageCount = ceil($lineCount / $linesPerPage);

    // calculate the page number we'll actually be returning
    $page = min($page, $pageCount);

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

    // Clean up temporary file
    unlink($tmpFilePath);

    // Read in CLF header name array from loghead.json
    $headers = json_decode(file_get_contents('clf/loghead.json'));

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

    // Output $logLines, $page and $lineCount as a JSON dictionary
    echo json_encode([
        'page' => $page,
        'pageCount' => $pageCount,
        'lineCount' => $lineCount,
        'logLines' => $logLines
    ]);
}
