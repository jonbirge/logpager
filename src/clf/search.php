<?php

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
            $matches = strpos($data[4], $value) !== false;
            break;
        case 'serv':
            // CLF logs don't have service field
            $matches = false;
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
    $terms = $searchDict['terms'];
    $operators = $searchDict['operators'];

    if (empty($terms)) {
        return true;
    }

    // Evaluate first term
    $result = evaluateTerm($terms[0], $data, $line);

    // Process remaining terms with operators
    for ($i = 1; $i < count($terms); $i++) {
        $operator = $operators[$i - 1] ?? 'AND'; // Default to AND
        $termResult = evaluateTerm($terms[$i], $data, $line);

        if ($operator === 'AND') {
            $result = $result && $termResult;
        } else if ($operator === 'OR') {
            $result = $result || $termResult;
        }
    }

    return $result;
}

// Evaluate legacy search query
function evaluateLegacySearch($searchDict, $data, $line)
{
    $search = $searchDict['search'] ?? null;
    $ip = $searchDict['ip'] ?? null;
    $date = $searchDict['date'] ?? null;
    $stat = $searchDict['stat'] ?? null;
    $details = $searchDict['details'] ?? null;

    // If $ip is set, skip this line if it doesn't contain $ip
    if ($ip !== null && strpos($data[1], $ip) === false) {
        return false;
    }

    // If $date is set, skip this line if it doesn't contain $date
    if ($date !== null && strpos($data[2], $date) === false) {
        return false;
    }

    // If $stat is set, skip this line if it doesn't contain $stat
    if ($stat !== null && strpos($data[4], $stat) === false) {
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
    $maxItems = 1024;  // summary items
    $maxSearchLines = 100000;  // matching lines

    // Path to the CLF log file
    $logFilePath = '/clf.log';
    $escFilePath = escapeshellarg($logFilePath);

    // Determine search mode
    $mode = $searchDict['mode'] ?? 'legacy';

    // Build UNIX command - in boolean mode, we just use tac without grep
    // to avoid pre-filtering, which allows us to handle complex boolean logic
    if ($mode === 'boolean') {
        $cmd = "tac $escFilePath | head -n $maxSearchLines";
    } else {
        // get search parameters for legacy mode
        $search = $searchDict['search'] ?? null;
        $ip = $searchDict['ip'] ?? null;
        $date = $searchDict['date'] ?? null;
        $stat = $searchDict['stat'] ?? null;

        // build grep command
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

        // If no search terms, skip grep (return all logs)
        if ($grepSearch === '') {
            $cmd = "tac $escFilePath | head -n $maxSearchLines";
        } else {
            $cmd = "tac $escFilePath | grep -m $maxSearchLines $grepSearch";
        }
    }

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
    if ($doSummary) {  // return summary 
        $searchLines = searchStats($logLines);
        // take the first $maxItems items
        $searchLines = array_slice($searchLines, 0, $maxItems + 1);
        echo json_encode($searchLines);
    } else {  // return standard log 
        // read in loghead.json and prepend to $logLines to create $searchLines
        $headers = json_decode(file_get_contents('clf/loghead.json'));
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
