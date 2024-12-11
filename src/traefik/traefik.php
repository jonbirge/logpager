<?php

// Return list of auth log files
function getTraefikLogFiles()
{
    // Array of log files to read
    $logFilePaths = ['/access.log.1', '/access.log'];

    // Remove any log files that don't exist
    foreach ($logFilePaths as $key => $logFilePath) {
        if (!file_exists($logFilePath)) {
            unset($logFilePaths[$key]);
        }
    }

    return $logFilePaths;
}

// Concatenate all log files into a temp file and return its path
function getTempLogFilePath()
{
    // Retrieve log file paths using getTraefikLogFiles()
    $logFilePaths = getTraefikLogFiles();

    // Create random temporary file path
    $tmpFilePath = '/tmp/traefiklog-' . bin2hex(random_int(0, PHP_INT_MAX)) . '.log';

    // Generate cat command to concatenate all log files
    $catCmd = 'cat ' . implode(' ', $logFilePaths);

    // Build and execute UNIX command to generate full log
    $cmd = "$catCmd > $tmpFilePath";
    exec($cmd);

    return $tmpFilePath;
}
