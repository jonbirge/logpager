<?php

// Field synonym mappings - allows flexible field names
function getFieldSynonyms()
{
    return array(
        'ip' => ['ip', 'addr', 'address'],
        'date' => ['date', 'time', 'when'],
        'stat' => ['stat', 'status', 'code'],
        'serv' => ['serv', 'service', 'server']
    );
}

// Parse a single term and extract field:value pairs
function parseTerm($term)
{
    $term = trim($term);
    $synonyms = getFieldSynonyms();

    // Check for field:value pattern
    foreach ($synonyms as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            $pattern = '/^' . preg_quote($alias, '/') . ':/i';
            if (preg_match($pattern, $term, $matches)) {
                $value = preg_replace($pattern, '', $term);
                return array(
                    'field' => $canonical,
                    'value' => trim($value),
                    'negate' => false
                );
            }
        }
    }

    // No field prefix found, treat as free text search
    return array(
        'field' => 'search',
        'value' => $term,
        'negate' => false
    );
}

// Parse search string with boolean operators
function parseSearch($search)
{
    if (!$search || trim($search) === '') {
        return null;
    }

    $search = trim($search);

    // Check if the search contains boolean operators
    $hasBoolean = preg_match('/\s+(AND|OR)\s+/i', $search);

    if (!$hasBoolean) {
        // Legacy mode: backward compatibility with old search format
        return parseLegacySearch($search);
    }

    // Parse boolean expression
    $terms = [];
    $operators = [];

    // Split by AND and OR operators while preserving the operator type
    // This regex captures the operators as well
    $parts = preg_split('/\s+(AND|OR)\s+/i', $search, -1, PREG_SPLIT_DELIM_CAPTURE);

    for ($i = 0; $i < count($parts); $i++) {
        $part = trim($parts[$i]);

        if ($part === '') {
            continue;
        }

        // Check if this is an operator
        if (preg_match('/^(AND|OR)$/i', $part)) {
            $operators[] = strtoupper($part);
        } else {
            // Check for NOT prefix
            $negate = false;
            if (preg_match('/^NOT\s+/i', $part)) {
                $negate = true;
                $part = preg_replace('/^NOT\s+/i', '', $part);
            }

            $term = parseTerm($part);
            $term['negate'] = $negate;
            $terms[] = $term;
        }
    }

    return array(
        'terms' => $terms,
        'operators' => $operators,
        'mode' => 'boolean'
    );
}

// Legacy search parser for backward compatibility
function parseLegacySearch($search)
{
    $ip = null;
    $dateStr = null;
    $stat = null;
    $serv = null;

    $search = trim($search);
    $synonyms = getFieldSynonyms();

    // Extract field filters using flexible prefixes
    foreach ($synonyms as $canonical => $aliases) {
        foreach ($aliases as $alias) {
            $pattern = '/' . preg_quote($alias, '/') . ':([^\s]+)/i';
            if (preg_match($pattern, $search, $matches)) {
                $$canonical = $matches[1];
                $search = preg_replace($pattern, '', $search);
                break; // Only take first match for each field
            }
        }
    }

    $search = trim($search);
    if ($search === '') {
        $search = null;
    }

    return array(
        'search' => $search,
        'ip' => $ip,
        'date' => $dateStr,
        'stat' => $stat,
        'serv' => $serv,
        'mode' => 'legacy'
    );
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
