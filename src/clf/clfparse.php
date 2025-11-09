<?php

// Include the logfiles utility
include_once __DIR__ . '/../logfiles.php';

// Return list of CLF log files
function getCLFLogFiles()
{
    // Use directory-based log file discovery
    $logFilePaths = getLogFilesFromDirectory('/log/clf');
    
    // Fallback to old behavior if directory doesn't exist
    if (empty($logFilePaths)) {
        $logFilePaths = ['/clf.log', '/clf.log.1'];
        
        // Remove any log files that don't exist
        foreach ($logFilePaths as $key => $logFilePath) {
            if (!file_exists($logFilePath)) {
                unset($logFilePaths[$key]);
            }
        }
    }

    return $logFilePaths;
}

// Concatenate all CLF log files into a temp file and return its path
function getCLFTempLogFilePath()
{
    // Retrieve log file paths using getCLFLogFiles()
    $logFilePaths = getCLFLogFiles();

    // Reverse the array to get oldest files first for chronological order
    $logFilePaths = array_reverse($logFilePaths);

    // Create random temporary file path
    $tmpFilePath = '/tmp/clflog-' . bin2hex(random_int(0, PHP_INT_MAX)) . '.log';

    // Generate cat command to concatenate all log files
    $catCmd = 'cat ' . implode(' ', $logFilePaths);

    // Build and execute UNIX command to generate full log
    $cmd = "$catCmd > $tmpFilePath";
    exec($cmd);

    return $tmpFilePath;
}
