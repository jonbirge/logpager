<?php

function getTraefikLogFiles()
{
    $logFilePaths = ['/access.log.1', '/access.log'];

    foreach ($logFilePaths as $key => $logFilePath) {
        if (!file_exists($logFilePath)) {
            unset($logFilePaths[$key]);
        }
    }

    return $logFilePaths;
}

function hourStr($hour)
{
    return $hour < 10 ? "0$hour" : "$hour";
}

function heatmap($searchDict)
{
    // set $doSearch to false if $searchDict is empty
    $doSearch = !empty($searchDict);

    $logFilePaths = getTraefikLogFiles();
    $logSummary = [];

    foreach ($logFilePaths as $logFilePath) {
        $handle = fopen($logFilePath, 'r');
        // If the file wasn't opened, continue to the next file
        if (!$handle) {
            echo "<p>Unable to open file: $logFilePath</p>";
            continue;
        }

        while (($line = fgets($handle)) !== false) {
            if (empty($line)) {
                continue;
            }

            $logEntry = explode(' ', $line, 9); // Limit the number of splits to 9 for efficiency
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
        fclose($handle);
    }

    echo json_encode($logSummary);
}
