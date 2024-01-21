<?php

$logFiles = [
    '/access.log',
    '/auth.log'
];

$existingLogFiles = [];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        $existingLogFiles[] = $logFile;
    }
}

// return existing log files as JSON
echo json_encode($existingLogFiles);

?>
