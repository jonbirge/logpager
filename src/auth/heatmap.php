<?php

// Define the cache file name as a constant
define('CACHE_FILE', '/tmp/auth_heatmap_cache.json');
define('CACHE_LIFE', 15);  // cache life in minutes

// Include the authparse.php file
include 'authparse.php';

// Evaluate if a single term matches the log data
function evaluateHeatmapTerm($term, $data, $status, $line)
{
    $field = $term['field'];
    $value = $term['value'];
    $matches = false;

    switch ($field) {
        case 'ip':
            $matches = strpos($data[0], $value) !== false;
            break;
        case 'date':
            $matches = strpos($data[1], $value) !== false;
            break;
        case 'stat':
            $matches = $status === $value;
            break;
        case 'search':
            $matches = strpos($line, $value) !== false;
            break;
        case 'serv':
            // Auth logs don't have service field
            $matches = false;
            break;
    }

    // Apply negation if needed
    if ($term['negate']) {
        $matches = !$matches;
    }

    return $matches;
}

// Evaluate boolean search query for heatmap
function evaluateHeatmapBooleanSearch($searchDict, $data, $status, $line)
{
    $terms = $searchDict['terms'];
    $operators = $searchDict['operators'];

    if (empty($terms)) {
        return true;
    }

    // Evaluate first term
    $result = evaluateHeatmapTerm($terms[0], $data, $status, $line);

    // Process remaining terms with operators
    for ($i = 1; $i < count($terms); $i++) {
        $operator = $operators[$i - 1] ?? 'AND'; // Default to AND
        $termResult = evaluateHeatmapTerm($terms[$i], $data, $status, $line);

        if ($operator === 'AND') {
            $result = $result && $termResult;
        } else if ($operator === 'OR') {
            $result = $result || $termResult;
        }
    }

    return $result;
}

// Evaluate legacy search query for heatmap
function evaluateHeatmapLegacySearch($searchDict, $data, $status, $line)
{
    $search = $searchDict['search'] ?? null;
    $ip = $searchDict['ip'] ?? null;
    $dateStr = $searchDict['date'] ?? null;
    $stat = $searchDict['stat'] ?? null;

    // Check if $status matches $stat exactly
    if ($stat && $status !== $stat) {
        return false;
    }

    // If $ip is set, check if $data[0] contains $ip
    if ($ip && strpos($data[0], $ip) === false) {
        return false;
    }

    // If $dateStr is set, check if $data[1] contains $dateStr
    if ($dateStr && strpos($data[1], $dateStr) === false) {
        return false;
    }

    // If $search is set, check if $line contains $search
    if ($search && strpos($line, $search) === false) {
        return false;
    }

    return true;
}

function readLines($fileHandle, $bufferSize = 2 * 1024 * 1024) {
    $lines = [];
    $buffer = '';

    while (!feof($fileHandle)) {
        $buffer .= fread($fileHandle, $bufferSize);

        // Continue reading until the end of the current line
        while (!feof($fileHandle) && substr($buffer, -1) !== "\n") {
            $buffer .= fread($fileHandle, 1);
        }

        // Split buffer into lines
        $bufferLines = explode("\n", $buffer);

        // Keep the last partial line in the buffer
        $buffer = array_pop($bufferLines);

        foreach ($bufferLines as $line) {
            $lines[] = $line;
        }
    }

    // Add any remaining partial line if not empty
    if (!empty($buffer)) {
        $lines[] = $buffer;
    }

    return $lines;
}

// Hour integer to string conversion function
function hourStr($hour)
{
    return str_pad($hour, 2, "0", STR_PAD_LEFT);
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

    // Current year
    $year = date('Y');

    // Log files to read
    $logFilePaths = getAuthLogFiles();

    // Initialize an empty array to store the log summary data
    $logSummary = [];

    // Iterate over each log file
    foreach ($logFilePaths as $filePath) {
        // Open the log file for reading
        $fileHandle = fopen($filePath, 'r');
        if (!$fileHandle) {
            echo "<p>Failed to open log file: $filePath</p>";
            continue;
        }

        // Read the log file in chunks
        while (!feof($fileHandle)) {
            // Read some lines from the log file
            $lines = readLines($fileHandle);

            foreach ($lines as $line) {
                // Skip lines that aren't sshd-related
                if (strpos($line, 'sshd') === false) {
                    continue;
                }

                // Skip lines that don't contain a valid IP address
                if (!preg_match('/([0-9]{1,3}\.){3}[0-9]{1,3}/', $line)) {
                    continue;
                }

                // Extract the timestamp from the auth log entry
                $data = parseAuthLogLine($line, $year);
                $timeStamp = $data[1];

                if ($doSearch) {
                    $status = getAuthLogStatus($line);

                    // Evaluate search based on mode
                    $matches = false;
                    if ($mode === 'boolean') {
                        $matches = evaluateHeatmapBooleanSearch($searchDict, $data, $status, $line);
                    } else {
                        $matches = evaluateHeatmapLegacySearch($searchDict, $data, $status, $line);
                    }

                    if (!$matches) {
                        continue;
                    }
                }

                // Convert the timestamp to a DateTime object
                $date = DateTime::createFromFormat('d/M/Y:H:i:s', $timeStamp);

                // Check if the DateTime object was created successfully
                if ($date !== false) {
                    // Get the date in the format YYYY-MM-DD
                    $dayOfYear = $date->format('Y-m-d');
                    // Get the hour of the day
                    $hour = $date->format('G'); // 24-hour format without leading zeros
                } else {
                    echo "<p>Invalid timestamp format encountered: $timeStamp</p>";
                    continue;
                }

                $hStr = hourStr($hour);
                $logSummary[$dayOfYear][$hStr] = ($logSummary[$dayOfYear][$hStr] ?? 0) + 1;
            }
        }

        // Close the file
        fclose($fileHandle);
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
