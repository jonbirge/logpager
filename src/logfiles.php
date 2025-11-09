<?php

// Maximum number of log files to concatenate for each log type
define('MAX_LOG_FILES', 2);

/**
 * Scan a directory for log files with .log suffix or .log.# format
 * Returns an array of file paths sorted by modification time (newest first)
 * 
 * @param string $directory The directory to scan for log files
 * @param int $maxFiles Maximum number of log files to return (default: MAX_LOG_FILES)
 * @param string|null $prefix Optional prefix that resulting files must start with
 * @return array Array of log file paths sorted by modification time (newest first)
 */
function getLogFilesFromDirectory($directory, $maxFiles = MAX_LOG_FILES, $prefix = null)
{
    $logFiles = [];
    
    // Check if directory exists
    if (!is_dir($directory)) {
        return $logFiles;
    }
    
    // Scan directory for files
    $files = scandir($directory);
    if ($files === false) {
        return $logFiles;
    }
    
    foreach ($files as $file) {
        // Skip . and ..
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $filePath = $directory . '/' . $file;
        
        // Check if it's a regular file
        if (!is_file($filePath)) {
            continue;
        }

        // Check if file matches .log or .log.# pattern
        if (preg_match('/\.log(\.\d+)?$/', $file)) {
            if ($prefix !== null && strpos($file, $prefix) !== 0) {
                continue;
            }
            $logFiles[] = $filePath;
        }
    }
    
    // Sort by modification time (newest first)
    usort($logFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Limit to maxFiles
    return array_slice($logFiles, 0, $maxFiles);
}
