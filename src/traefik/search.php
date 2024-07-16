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
    $serv = $searchDict['serv'];

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
    if ($serv) {
        $grepSearch .= " -e $serv";
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
        preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) \S+ \"-\" \"-\" \S+ \"(\S+)\" \"\S+\" \S+/', $line, $data);

        // swap the last two matches so the status is always last
        $temp = $data[4];
        $data[4] = $data[5];
        $data[5] = $temp;

        // If $ip is set, skip this line if it doesn't contain $ip
        if ($ip !== null && strpos($data[1], $ip) === false) {
            continue;
        }

        // If $date is set, skip this line if it doesn't contain $date
        if ($date !== null && strpos($data[2], $date) === false) {
            continue;
        }

        // If $serv is set, skip this line if it doesn't contain $serv
        if ($serv !== null && strpos($data[4], $serv) === false) {
            continue;
        }

        // If $stat is set, skip this line if it doesn't contain $stat
        if ($stat !== null && strpos($data[5], $stat) === false) {
            continue;
        }

        // If $search is set, skip this line if it doesn't contain $search
        if ($search !== null && strpos($line, $search) === false) {
            continue;
        }

        $lineCount++;

        // If we're summarizing, store less data and use a date object
        if ($doSummary) {
            // convert the standard log date format to a PHP DateTime object
            $theDate = $data[2];
            $theDate = preg_replace('/\s+\S+$/', '', $theDate);
            $dateObj = DateTime::createFromFormat('d/M/Y:H:i:s', $theDate);
            if ($dateObj === false) {
                echo "Error parsing date: $theDate\n";
            }
            $logLines[] = [$data[1], $dateObj, $data[5]];  // IP, date, stat
        } else {
            $logLines[] = array_map('htmlspecialchars', array_slice($data, 1));
            if ($lineCount >= $maxItems) break;
        }
    }

    // If $doSummary is true, run the statistics function
    if ($doSummary) {  // return summary 
        $searchLines = searchStats($logLines);
        $searchLines = array_slice($searchLines, 0, $maxItems + 1);  // take the first $maxItems items
        echo json_encode($searchLines);
    } else {  // return standard log 
        // read in loghead.json and prepend to $logLines to create $searchLines
        $headers = json_decode(file_get_contents('traefik/loghead.json'));
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
