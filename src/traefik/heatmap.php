<?php

// Define the cache file name as a constant
define('CACHE_FILE', '/tmp/traefik_heatmap_cache.json');
define('CACHE_LIFE', 15);  // cache life in minutes

include 'traefik.php';

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

            // Skip unnecessary checks early
            if ($doSearch) {
                if (($searchDict['ip'] && strpos($ipAddress, $searchDict['ip']) === false) ||
                    ($searchDict['date'] && strpos($timeStamp, $searchDict['date']) === false) ||
                    ($searchDict['stat'] && $logEntry[8] !== $searchDict['stat']) ||
                    ($searchDict['search'] && strpos($line, $searchDict['search']) === false)
                ) {
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
