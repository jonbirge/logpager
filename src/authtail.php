<?php

// Include the authparse.php file
include 'authparse.php';

function authTail($page, $linesPerPage)
{
    // Path to the auth log file
    $logFilePaths = getAuthLogFiles();

    // generate UNIX grep command line argument to only include lines containing IP addresses
    $grepIPCmd = "grep -E '([0-9]{1,3}\.){3}[0-9]{1,3}'";

    // generate UNIX grep command line arguments to include services we care about
    $services = ['sshd'];
    $grepArgs = '';
    foreach ($services as $service) {
        $grepArgs .= " -e $service";
    }
    $grepSrvCmd = "grep $grepArgs";

    // generate cat command to concatenate all log files
    $catCmd = 'cat ' . implode(' ', $logFilePaths);

    // build UNIX command
    $firstLine = $page * $linesPerPage + 1;
    $lastLine = $firstLine + ($linesPerPage - 1);
    $cmd = "$catCmd | $grepSrvCmd | $grepIPCmd | nl | (echo 'BEGIN'; cat) | tail -n $lastLine | head -n $linesPerPage | tac";

    // read the lines from UNIX pipe
    $fp = popen($cmd, 'r');
    $lines = [];
    while ($line = fgets($fp)) {
        $lines[] = $line;
    }
    pclose($fp);

    // Read in CLF header name array from clfhead.json
    $headers = json_decode(file_get_contents('loghead.json'));

    // Create array of auth log lines
    $logLines = [];
    $logLines[] = $headers;

    // Process each line and add to the array
    $lineCount = 0;
    foreach ($lines as $line) {
        // check to see if $line is the BEGIN line
        if (strpos($line, 'BEGIN') === 0) {
            // repeat the string "END" in an array the size of $headers
            $logLines[] = array_fill(0, count($headers), 'END');
            break;
        }

        // pull line number off front of line
        if (!preg_match('/^\s*(\d+)\s+(.*)/', $line, $matches)) {
            return false; // handle error as appropriate
        }
        $lineNumber = $matches[1];
        $line = $matches[2];

        // parse the rest of the line
        $data = parseAuthLogLine($line);

        // determine status based on message
        $status = getAuthLogStatus($data[2]);

        $logLines[] = [$lineNumber, $data[0], $data[1], $data[2], $status];
        $lineCount++;
        if ($lineCount >= $linesPerPage) {
            break;
        }
    }

    // Output the array as JSON
    echo json_encode($logLines);
}
