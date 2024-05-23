<?php

function search($searchDict, $doSummary = true)
{
    // Maximum number of items to return
    $maxItems = 1024;  // summary items
    $maxSearchLines = 100000;  // matching lines

    // Path to the CLF log file
    $logFilePath = '/access.log';
    $escFilePath = escapeshellarg($logFilePath);

    // get search parameters
    $search = $searchDict['search'];
    $ip = $searchDict['ip'];
    $date = $searchDict['date'];
    $stat = $searchDict['stat'];

    // build UNIX command
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
    $cmd = "tac $escFilePath | grep -m $maxSearchLines $grepSearch";

    // execute UNIX command and read lines from pipe
    $fp = popen($cmd, 'r');
    $lines = [];
    while ($line = fgets($fp)) {
        $lines[] = $line;
    }
    pclose($fp);

    // Create array of CLF log lines
    $logLines = [];

    // Process each line and add to the array
    $lineCount = 0;
    foreach ($lines as $line) {
        // Extract the CLF fields from the line
        preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+)/', $line, $data);

        // If $ip is set, skip this line if it doesn't contain $ip
        if ($ip !== null && strpos($data[1], $ip) === false) {
            continue;
        }

        // If $date is set, skip this line if it doesn't contain $date
        if ($date !== null && strpos($data[2], $date) === false) {
            continue;
        }

        // If $stat is set, skip this line if it doesn't contain $stat
        if ($stat !== null && strpos($data[4], $stat) === false) {
            continue;
        }

        // If $search is set, skip this line if it doesn't contain $search
        if ($search !== null && strpos($line, $search) === false) {
            continue;
        }

        $lineCount++;

        if ($doSummary) {
            // convert the standard log date format (e.g. 18/Jan/2024:17:47:55) to a PHP DateTime object,  ignoring the timezone part
            $theDate = $data[2];
            // remove time zone from $theDate
            $theDate = preg_replace('/\s+\S+$/', '', $theDate);
            $dateObj = DateTime::createFromFormat('d/M/Y:H:i:s', $theDate);
            if ($dateObj === false) {
                echo "Error parsing date: $theDate\n";
            }
            $logLines[] = [$data[1], $dateObj, $data[4]];
        } else {
            $logLines[] = array_map('htmlspecialchars', array_slice($data, 1));
            if ($lineCount >= $maxItems) break;
        }
    }

    // If $doSummary is true, summarize the log lines
    if ($doSummary) { // return summary 
        $searchLines = searchStats($logLines);
        // take the first $maxItems items
        $searchLines = array_slice($searchLines, 0, $maxItems + 1);
        echo json_encode($searchLines);
    } else { // return standard log 
        $searchLines = searchLines($logLines);
        echo json_encode([
            'page' => 0,
            'pageCount' => 0,
            'lineCount' => count($searchLines) - 1,
            'logLines' => $searchLines,
            'search' => $searchDict
        ]);
    }
}
