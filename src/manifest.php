<?php

include_once __DIR__ . '/logfiles.php';

// Map manifest entries to their on-disk directories
$logDirectories = [
    'auth.log' => '/log/auth',
    'clf.log' => '/log/clf',
    'access.log' => '/log/traefik',
];

$existingLogFiles = [];

foreach ($logDirectories as $manifestName => $directory) {
    // We only need to know if at least one .log file exists in the directory
    $logFiles = getLogFilesFromDirectory($directory, 1);
    if (!empty($logFiles)) {
        $existingLogFiles[] = $manifestName;
    }
}

echo json_encode($existingLogFiles);
