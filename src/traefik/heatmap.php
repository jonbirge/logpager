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

function getLogFileContent()
{
    $logFilePaths = getTraefikLogFiles();
    $logContent = '';

    foreach ($logFilePaths as $logFilePath) {
        $logContent .= file_get_contents($logFilePath);
    }

    return $logContent;
}

function heatmap($searchDict)
{
    $logContent = getLogFileContent();

    $search = $searchDict['search'];
    $ip = $searchDict['ip'];
    $dateStr = $searchDict['date'];
    $stat = $searchDict['stat'];

    $logLines = explode("\n", $logContent);
    $logSummary = [];

    foreach ($logLines as $line) {
        if (empty($line)) {
            continue;
        }

        $logEntry = explode(' ', $line);
        $ipAddress = $logEntry[0];
        $timeStamp = $logEntry[3];
        $date = DateTime::createFromFormat('[d/M/Y:H:i:s', $timeStamp);

        if ($ip && strpos($ipAddress, $ip) === false) {
            continue;
        }

        if ($dateStr && strpos($timeStamp, $dateStr) === false) {
            continue;
        }

        if ($stat) {
            $status = $logEntry[8];
            if ($status !== $stat) {
                continue;
            }
        }

        if ($search && strpos($line, $search) === false) {
            continue;
        }

        if ($date !== false) {
            $dayOfYear = $date->format('Y-m-d');
            $hour = $date->format('G');
        } else {
            echo "<p>Invalid timestamp format encountered: $timeStamp</p>";
            return;
        }

        $hStr = hourStr($hour);
        if (!isset($logSummary[$dayOfYear][$hStr])) {
            $logSummary[$dayOfYear][$hStr] = 0;
        }
        $logSummary[$dayOfYear][$hStr]++;
    }

    echo json_encode($logSummary);
}

function hourStr($hour)
{
    return $hour < 10 ? "0$hour" : "$hour";
}
