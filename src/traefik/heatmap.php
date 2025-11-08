<?php

// Define the cache file name as a constant
define('CACHE_FILE', '/tmp/traefik_heatmap_cache.json');
define('CACHE_LIFE', 15);  // cache life in minutes

include 'traefik.php';

// Evaluate if a single term matches the log data
function evaluateHeatmapTerm($term, $ipAddress, $timeStamp, $status, $line)
{
    $field = $term['field'];
    $value = $term['value'];
    $matches = false;

    switch ($field) {
        case 'ip':
            $matches = strpos($ipAddress, $value) !== false;
            break;
        case 'date':
            $matches = strpos($timeStamp, $value) !== false;
            break;
        case 'stat':
            $matches = strpos($status, $value) !== false;
            break;
        case 'serv':
            // For heatmap, we don't have easy access to service field
            // Would need to parse the full log line
            $matches = strpos($line, $value) !== false;
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

// Evaluate boolean search query for heatmap
function evaluateHeatmapBooleanSearch($searchDict, $ipAddress, $timeStamp, $status, $line)
{
    $terms = $searchDict['terms'];
    $operators = $searchDict['operators'];

    if (empty($terms)) {
        return true;
    }

    // Evaluate first term
    $result = evaluateHeatmapTerm($terms[0], $ipAddress, $timeStamp, $status, $line);

    // Process remaining terms with operators
    for ($i = 1; $i < count($terms); $i++) {
        $operator = $operators[$i - 1] ?? 'AND'; // Default to AND
        $termResult = evaluateHeatmapTerm($terms[$i], $ipAddress, $timeStamp, $status, $line);

        if ($operator === 'AND') {
            $result = $result && $termResult;
        } else if ($operator === 'OR') {
            $result = $result || $termResult;
        }
    }

    return $result;
}

// Evaluate legacy search query for heatmap
function evaluateHeatmapLegacySearch($searchDict, $ipAddress, $timeStamp, $status, $line)
{
    $search = $searchDict['search'] ?? null;
    $ip = $searchDict['ip'] ?? null;
    $date = $searchDict['date'] ?? null;
    $stat = $searchDict['stat'] ?? null;

    if ($ip && strpos($ipAddress, $ip) === false) {
        return false;
    }

    if ($date && strpos($timeStamp, $date) === false) {
        return false;
    }

    if ($stat && strpos($status, $stat) === false) {
        return false;
    }

    if ($search && strpos($line, $search) === false) {
        return false;
    }

    return true;
}

function hourStr($hour)
{
    return $hour < 10 ? "0$hour" : "$hour";
}

function heatmap($searchDict)
{
    // set $doSearch to false if $searchDict is empty
    $doSearch = !empty($searchDict);

    // if searching, always generate a new summary
    if ($doSearch) {
        genLogSummary($searchDict);
        return;
    }

    // check the cache file for a valid cache
    if (file_exists(CACHE_FILE)) {
        $cacheData = json_decode(file_get_contents(CACHE_FILE), true);
        $cacheTime = strtotime($cacheData['generatedAt']);
        $currentTime = time();
        $cacheAge = ($currentTime - $cacheTime) / 60;  // cache age in minutes

        // if cache is fresh, echo the cache data and return
        if ($cacheAge < CACHE_LIFE) {
            echo json_encode($cacheData['logSummary']);
            return;
        }
    }
    
    // if all else fails, generate a new log summary
    genLogSummary();
}

function genLogSummary($searchDict = [])
{
    // set $doSearch to false if $searchDict is empty
    $doSearch = !empty($searchDict);

    // Determine search mode
    $mode = $searchDict['mode'] ?? 'legacy';

    $logFilePaths = getTraefikLogFiles();
    $logSummary = [];

    foreach ($logFilePaths as $logFilePath) {
        $handle = fopen($logFilePath, 'r');
        if (!$handle) {
            echo "<p>Unable to open file: $logFilePath</p>";
            continue;
        }

        while (($line = fgets($handle)) !== false) {
            if (empty($line)) {
                continue;
            }

            $logEntry = explode(' ', $line, 9);
            $ipAddress = $logEntry[0];
            $timeStamp = $logEntry[3];
            $status = $logEntry[8];

            // Skip unnecessary checks early
            if ($doSearch) {
                // Evaluate search based on mode
                $matches = false;
                if ($mode === 'boolean') {
                    $matches = evaluateHeatmapBooleanSearch($searchDict, $ipAddress, $timeStamp, $status, $line);
                } else {
                    $matches = evaluateHeatmapLegacySearch($searchDict, $ipAddress, $timeStamp, $status, $line);
                }

                if (!$matches) {
                    continue;
                }
            }

            $date = DateTime::createFromFormat('[d/M/Y:H:i:s', $timeStamp);
            if ($date === false) {
                echo "<p>Invalid timestamp format encountered: $timeStamp</p>";
                return;
            }

            $dayOfYear = $date->format('Y-m-d');
            $hour = $date->format('G');
            $hStr = hourStr($hour); // Use hourStr function
            $logSummary[$dayOfYear][$hStr] = ($logSummary[$dayOfYear][$hStr] ?? 0) + 1;
        }

        // Close the file
        fclose($handle);
    }

    // Echo the log summary data as JSON, ASAP
    echo json_encode($logSummary);

    // Cache the results unless a search was performed
    if (!$doSearch) {
        $cacheData = [
            'generatedAt' => date('c'),
            'logSummary' => $logSummary
        ];
        file_put_contents(CACHE_FILE, json_encode($cacheData));
    }
}
