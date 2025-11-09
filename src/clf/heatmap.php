<?php

// Include the clfparse.php file
include 'clfparse.php';

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
            $matches = $status === $value;
            break;
        case 'serv':
            // CLF logs don't have service field
            $matches = false;
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
    $dateStr = $searchDict['date'] ?? null;
    $stat = $searchDict['stat'] ?? null;

    // If $ip is set, check if $ipAddress contains $ip
    if ($ip && strpos($ipAddress, $ip) === false) {
        return false;
    }

    // If $dateStr is set, check if $date contains $dateStr
    if ($dateStr && strpos($timeStamp, $dateStr) === false) {
        return false;
    }

    // If $stat is set, check if $status matches $stat
    if ($stat && $status !== $stat) {
        return false;
    }

    // If $search is set, check if $line contains $search
    if ($search && strpos($line, $search) === false) {
        return false;
    }

    return true;
}

function heatmap($searchDict)
{
    // Get the concatenated log file path
    $tmpFilePath = getCLFTempLogFilePath();

    // Determine search mode
    $mode = $searchDict['mode'] ?? 'legacy';
    $doSearch = !empty($searchDict);

    // Open the log file for reading
    $logFile = fopen($tmpFilePath, 'r');
    if (!$logFile) {
        echo "<p>Failed to open log file.</p>";
        unlink($tmpFilePath);
        return;
    }

    // Initialize an empty array to store the log summary data
    $logSummary = [];

    // Read each line of the log file
    while (($line = fgets($logFile)) !== false) {
        // Extract the elements from the CLF log entry
        $logEntry = explode(' ', $line);

        // Extract the IP address from the CLF log entry
        $ipAddress = $logEntry[0];

        // Extract the timestamp from the CLF log entry
        $timeStamp = $logEntry[3];

        // Extract the status from the CLF log entry
        $status = $logEntry[8] ?? null;

        // Apply search filters if needed
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

        // Convert the timestamp to a DateTime object
        $date = DateTime::createFromFormat('[d/M/Y:H:i:s', $timeStamp);

        // Check if the DateTime object was created successfully
        if ($date !== false) {
            // Get the date in the format YYYY-MM-DD
            $dayOfYear = $date->format('Y-m-d');
            // Get the hour of the day
            $hour = $date->format('G'); // 24-hour format without leading zeros
        } else {
            echo "<p>Invalid timestamp format encountered: $timeStamp</p>";
            return;
        }

        // Initialize the count for the day of the year and hour of the day
        $hStr = hourStr($hour);
        if (!isset($logSummary[$dayOfYear][$hStr])) {
            $logSummary[$dayOfYear][$hStr] = 0;
        }
        // Increment the count for the day of the year and hour of the day
        $logSummary[$dayOfYear][$hStr]++;
    }

    // Close the log file
    fclose($logFile);

    // Clean up temporary file
    unlink($tmpFilePath);

    // Echo the log summary data as JSON
    echo json_encode($logSummary);
}

// Hour integer to string conversion function
function hourStr($hour)
{
    if ($hour < 10) {
        return "0$hour";
    } else {
        return "$hour";
    }
}
