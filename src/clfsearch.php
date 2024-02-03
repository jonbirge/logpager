<?php

function clfSearch($searchDict)
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
    $cmd = "grep $grepSearch $escFilePath";

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

        // convert the standard log date format (e.g. 18/Jan/2024:17:47:55) to a PHP DateTime object,  ignoring the timezone part
        $theDate = $data[2];
        // remove time zone from $theDate
        $theDate = preg_replace('/\s+\S+$/', '', $theDate);
        $dateObj = DateTime::createFromFormat('d/M/Y:H:i:s', $theDate);
        if ($dateObj === false) {
            echo "Error parsing date: $theDate\n";
        }

        $logLines[] = [$data[1], $dateObj, $data[2], $data[4]];
    }

    // Create an array indexed by IP address
    $ipDict = [];
    foreach ($logLines as $line) {
        $ip = $line[0];  // string
        $date = $line[1];  // DateTime object
        $status = $line[3];  // string

        if (!array_key_exists($ip, $ipDict)) {
            $ipDict[$ip] = ['totalCount' => 0, 'lastDate' => null, 'failCount' => 0];
        }

        $ipDict[$ip]['totalCount'] += 1;

        // Update lastDate if the current date is more recent
        if ($ipDict[$ip]['lastDate'] === null || $date > $ipDict[$ip]['lastDate']) {
            $ipDict[$ip]['lastDate'] = $date;
        }
        
        if ($status === 'FAIL') {
            $ipDict[$ip]['failCount'] += 1;
        }
    }

    // Read in CLF header name array from searchhead.json
    $headers = json_decode(file_get_contents('searchhead.json'), true);

    // Write out the $ipDict as a table with the columns: Total, IP, Last, Fail
    $searchLines = [];
    foreach ($ipDict as $ip => $data) {
        $dateStr = $data['lastDate'] === null ? '-' : $data['lastDate']->format('d/M/Y:H:i:s');
        $searchLines[] = [$data['totalCount'], $ip, $dateStr, $data['failCount']];
    }

    // Sort $searchLines by the first column (Total) assuming they are integers
    usort($searchLines, function ($a, $b) {
        return $b[0] - $a[0];
    });

    // Add the header to the top of the array
    array_unshift($searchLines, $headers);

    // Output the log lines as JSON
    echo json_encode($searchLines);
}
