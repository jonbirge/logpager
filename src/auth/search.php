<?php

// Include the authparse.php file
include 'authparse.php';

function search($searchDict, $doSummary = true)
{
    // Parameters
    $maxItems = 2500;  // line items
    $maxLines = 1024;  // log lines
    $maxSummarize = 100000;  // matching lines

    // Current year
    $year = date('Y');

    // Path to the auth log file
    $logFilePaths = array_reverse(getAuthLogFiles());

    // get search parameters
    $search = $searchDict['search'];
    $ip = $searchDict['ip'];
    $date = $searchDict['date'];
    $stat = $searchDict['stat'];

    // generate UNIX grep command line argument to only include lines containing IP addresses
    $grepIPCmd = "grep -E '([0-9]{1,3}\.){3}[0-9]{1,3}'";

    // generate cat command to concatenate all log files
    $catCmd = 'tac ' . implode(' ', $logFilePaths);

    // build UNIX command
    $cmd = "$catCmd | $grepIPCmd";

    // execute the UNIX command
    $fp = popen($cmd, 'r');

    // Create array of auth log lines
    $logLines = [];

    // Process each line and add to the array
    $lineCount = 0;
    while ($line = fgets($fp)) {
        $data = parseAuthLogLine($line, $year);

        if ($data === false) {
            $logLines[] = ['-', '-', $line, 'ERROR'];
            continue;
        }

        // If $search is set, check if $data[2] contains $search
        if ($search) {
            if (strpos($data[2], $search) === false) {
                continue;
            }
        }

        // If $ip is set, check if $data[0] contains $ip
        if ($ip) {
            if (strpos($data[0], $ip) === false) {
                continue;
            }
        }

        // If $date is set, check if $data[1] contains $date
        if ($date) {
            if (strpos($data[1], $date) === false) {
                continue;
            }
        }

        // determine status based on $data[2]
        $status = getAuthLogStatus($data[2]);

        // If $stat is set, check if $status matches $stat
        if ($stat) {
            if ($status !== $stat) {
                continue;
            }
        }

        $lineCount++;
        if ($doSummary) {
            // convert the standard log date format (e.g. 18/Jan/2024:17:47:55) to a PHP DateTime object
            $theDate = $data[1];
            $dateObj = DateTime::createFromFormat('d/M/Y:H:i:s', $theDate);
            if ($dateObj === false) {
                echo "Error parsing date: $theDate\n";
            }
            $logLines[] = [$data[0], $dateObj, $status];
            if ($lineCount >= $maxSummarize) break;
        } else {
            $logLines[] = [$data[0], $data[1], $data[2], $status];
            if ($lineCount >= $maxLines) break;
        }
    }  // end foreach

    // Clean up
    pclose($fp);

    // If $doSummary is true, summarize the log lines
    if ($doSummary) { // return summary
        $searchLines = searchStats($logLines);
        $searchLines = array_slice($searchLines, 0, $maxItems + 1);
        echo json_encode($searchLines);
    } else {  // return standard log
        $headers = json_decode(file_get_contents('auth/loghead.json'));
        $searchLines = [];
        $searchLines[] = $headers;
        $searchLines = array_merge($searchLines, $logLines);
        echo json_encode([
            'page' => 0,
            'pageCount' => 0,
            'lineCount' => count($searchLines) - 1,
            'logLines' => $searchLines,
            'search' => $searchDict
        ]);
    }
}
