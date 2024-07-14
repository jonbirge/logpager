<?php

function parseSearch($search)
{
    // Check $search string for terms preceded by ip: or date: (etc.) and assume there is,
    // at most, one of each (for now). If $string is empty afterwards, set it to null.
    $ip = null;
    $dateStr = null;
    $stat = null;
    if ($search) {
        $search = trim($search);
        $ipPos = strpos($search, 'ip:');
        $datePos = strpos($search, 'date:');
        $statPos = strpos($search, 'stat:');
        if ($ipPos !== false) {
            $ip = substr($search, $ipPos + 3);
            $search = trim(substr($search, 0, $ipPos));
        }
        if ($datePos !== false) {
            $dateStr = substr($search, $datePos + 5);
            $search = trim(substr($search, 0, $datePos));
        }
        if ($statPos !== false) {
            $stat = substr($search, $statPos + 5);
            $search = trim(substr($search, 0, $statPos));
        }
        if ($search === '') {
            $search = null;
        }

        // return as dictionary array
        return array(
            'search' => $search,
            'ip' => $ip,
            'date' => $dateStr,
            'stat' => $stat
        );
    } else {
        return null;
    }
}

function searchStats($logLines)
{
    // Create an array indexed by IP address
    $ipDict = [];
    foreach ($logLines as $line) {
        $ip = $line[0];  // string
        $date = $line[1];  // DateTime object
        $status = $line[2];  // string

        if (!array_key_exists($ip, $ipDict)) {
            $ipDict[$ip] = ['totalCount' => 0, 'lastDate' => null, 'failCount' => 0];
        }

        $ipDict[$ip]['totalCount'] += 1;

        // Update lastDate if the current date is more recent
        if ($ipDict[$ip]['lastDate'] === null || $date > $ipDict[$ip]['lastDate']) {
            $ipDict[$ip]['lastDate'] = $date;
        }
        
        if ($status === 'FAIL' || $status === '404' || $status === '403') {
            $ipDict[$ip]['failCount'] += 1;
        }
    }

    // Read in CLF header name array from searchhead.json
    $headers = json_decode(file_get_contents('summaryhead.json'), true);

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

    return $searchLines;
}
