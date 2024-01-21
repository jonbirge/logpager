<?php

// parameters
$logFiles = [
    'access.log',
    'auth.log'
];
$filePrefix = '/';

// check for files
$existingLogFiles = [];
foreach ($logFiles as $logFile) {
    if (file_exists($filePrefix . $logFile)) {
        $existingLogFiles[] = $logFile;
    }
}

// return existing log files as JSON
echo json_encode($existingLogFiles);

?>
