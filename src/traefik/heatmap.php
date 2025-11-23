<?php

define('CACHE_FILE', '/tmp/traefik_heatmap_cache.json');
define('CACHE_LIFE', 15);  // cache life in minutes

include_once __DIR__ . '/../searchparse.php';
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
    return evaluateBooleanChain($searchDict, function ($term) use ($ipAddress, $timeStamp, $status, $line) {
        return evaluateHeatmapTerm($term, $ipAddress, $timeStamp, $status, $line);
    });
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

function parseTimestampParts($timeStamp)
{
    // Example: 05/Mar/2024:12:34:56 +0000
    $parts = sscanf($timeStamp, "%d/%3s/%d:%d:%d:%d %5s");
    if ($parts === null || count($parts) < 6) {
        return null;
    }

    [$day, $monthStr, $year, $hour, $minute, $second] = $parts;
    static $monthMap = [
        'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4,
        'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8,
        'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
    ];

    if (!isset($monthMap[$monthStr])) {
        return null;
    }

    $month = $monthMap[$monthStr];
    $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
    $monthStrNum = str_pad($month, 2, '0', STR_PAD_LEFT);

    return [
        'date' => $year . '-' . $monthStrNum . '-' . $dayStr,
        'hour' => $hour,
    ];
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
    $pattern = '/(\S+) \S+ \S+ \[(.+?)\] "(.*?)" (\S+) \S+ "-" "-" \S+ "(\S+)" "\S+" \S+/';

    foreach ($logFilePaths as $logFilePath) {
        $file = new SplFileObject($logFilePath, 'r');
        if (!$file) {
            echo "<p>Unable to open file: $logFilePath</p>";
            continue;
        }

        while (!$file->eof()) {
            $line = $file->fgets();
            if (empty($line)) {
                continue;
            }

            if (!preg_match($pattern, $line, $matches)) {
                continue;
            }

            // swap the last two matches so the status is always in index 4
            $temp = $matches[4];
            $matches[4] = $matches[5];
            $matches[5] = $temp;

            $ipAddress = $matches[1];
            $timeStamp = $matches[2];
            $status = $matches[4];

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

            $tsParts = parseTimestampParts($timeStamp);
            if ($tsParts === null) {
                continue;
            }

            $dayOfYear = $tsParts['date'];
            $hStr = hourStr($tsParts['hour']); // Use hourStr function
            $logSummary[$dayOfYear][$hStr] = ($logSummary[$dayOfYear][$hStr] ?? 0) + 1;
        }
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
