<?php

include_once __DIR__ . '/../searchparse.php';
include 'traefik.php';

// Evaluate if a single term matches the log data
function evaluateTerm($term, $data, $line)
{
    $field = $term['field'];
    $value = $term['value'];
    $matches = false;

    switch ($field) {
        case 'ip':
            $matches = strpos($data[1], $value) !== false;
            break;
        case 'date':
            $matches = strpos($data[2], $value) !== false;
            break;
        case 'stat':
            $matches = strpos($data[5], $value) !== false;
            break;
        case 'serv':
            $matches = strpos($data[4], $value) !== false;
            break;
        case 'details':
            $matches = strpos($data[3], $value) !== false;
            break;
        case 'search':
            $matches = strpos($line, $value) !== false;
            break;
    }

    // Apply negation if needed
    if ($term['negate']) {
        $matches = !$matches;
    }

    return $matches;
}

// Evaluate boolean search query
function evaluateBooleanSearch($searchDict, $data, $line)
{
    return evaluateBooleanChain($searchDict, function ($term) use ($data, $line) {
        return evaluateTerm($term, $data, $line);
    });
}

// Evaluate legacy search query
function evaluateLegacySearch($searchDict, $data, $line)
{
    $search = $searchDict['search'] ?? null;
    $ip = $searchDict['ip'] ?? null;
    $date = $searchDict['date'] ?? null;
    $stat = $searchDict['stat'] ?? null;
    $serv = $searchDict['serv'] ?? null;
    $details = $searchDict['details'] ?? null;

    // If $ip is set, skip this line if it doesn't contain $ip
    if ($ip !== null && strpos($data[1], $ip) === false) {
        return false;
    }

    // If $date is set, skip this line if it doesn't contain $date
    if ($date !== null && strpos($data[2], $date) === false) {
        return false;
    }

    // If $serv is set, skip this line if it doesn't contain $serv
    if ($serv !== null && strpos($data[4], $serv) === false) {
        return false;
    }

    // If $stat is set, skip this line if it doesn't contain $stat
    if ($stat !== null && strpos($data[5], $stat) === false) {
        return false;
    }

    // If $details is set, skip this line if it doesn't contain $details
    if ($details !== null && strpos($data[3], $details) === false) {
        return false;
    }

    // If $search is set, skip this line if it doesn't contain $search
    if ($search !== null && strpos($line, $search) === false) {
        return false;
    }

    return true;
}

function search($searchDict, $doSummary = true)
{
    // Maximum number of items to return
    $maxItems = 2500;  // line items
    $maxSearchLines = 100000;  // matching lines
    $maxSummarize = 100000;  // max summary items

    // TODO: Follow auth pattern here...
    // Concatenate log files
    $tmpFilePath = getTempLogFilePath();

    // Determine search mode
    $mode = $searchDict['mode'] ?? 'legacy';

    // Build UNIX command - in boolean mode, we just use tac without grep
    // to avoid pre-filtering, which allows us to handle complex boolean logic
    if ($mode === 'boolean') {
        $cmd = "tac $tmpFilePath | head -n $maxSearchLines";
    } else {
        // Get search parameters for legacy mode
        $search = $searchDict['search'] ?? null;
        $ip = $searchDict['ip'] ?? null;
        $date = $searchDict['date'] ?? null;
        $stat = $searchDict['stat'] ?? null;
        $serv = $searchDict['serv'] ?? null;

        // Build grep command
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

        // If no search terms, skip grep (return all logs)
        if ($grepSearch === '') {
            $cmd = "tac $tmpFilePath | head -n $maxSearchLines";
        } else {
            $cmd = "tac $tmpFilePath | grep -m $maxSearchLines $grepSearch";
        }
    }

    // Execute UNIX command and read lines from pipe
    $fp = popen($cmd, 'r');

    // Create array of CLF log lines
    $logLines = [];

    // Process each line and add to the array
    $lineCount = 0;
    while ($line = fgets($fp)) {
        // Extract the CLF fields from the line
        preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) \S+ \"-\" \"-\" \S+ \"(\S+)\" \"\S+\" \S+/', $line, $data);

        // swap the last two matches so the status is always last
        $temp = $data[4];
        $data[4] = $data[5];
        $data[5] = $temp;

        // Evaluate search based on mode
        $matches = false;
        if ($mode === 'boolean') {
            $matches = evaluateBooleanSearch($searchDict, $data, $line);
        } else {
            $matches = evaluateLegacySearch($searchDict, $data, $line);
        }

        if (!$matches) {
            continue;
        }

        $lineCount++;
        if ($doSummary) {
            // convert the standard log date format to a PHP DateTime object
            $theDate = $data[2];
            $theDate = preg_replace('/\s+\S+$/', '', $theDate);
            $dateObj = DateTime::createFromFormat('d/M/Y:H:i:s', $theDate);
            if ($dateObj === false) {
                echo "Error parsing date: $theDate\n";
            }
            $logLines[] = [$data[1], $dateObj, $data[5]];  // IP, date, stat
            if ($lineCount >= $maxSummarize) break;
        } else {
            $logLines[] = array_slice($data, 1);
            if ($lineCount >= $maxItems) break;
        }
    }

    // Clean up
    pclose($fp);
    unlink($tmpFilePath);

    // If $doSummary is true, summarize the log lines
    if ($doSummary) {  // return summary 
        $searchLines = searchStats($logLines);
        $searchLines = array_slice($searchLines, 0, $maxItems + 1);
        echo json_encode($searchLines);
    } else {  // return standard log 
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
