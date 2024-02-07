<?php
$user_ip = $_GET['ip'] ?? '';
$uniqueId = $_GET['id'] ?? '';
$scan_mode = $_GET['mode'] ?? 'fast';

// Execute the scan
$command_args = escapeshellarg($uniqueId) . " " . escapeshellarg($user_ip) . " -d > /dev/null 2>&1 &";
if ($scan_mode == 'deep') {
    $command = "./rundeepscan.sh " . $command_args;
} else {
    $command = "./runscan.sh " . $command_args;
}

echo("Starting server command: " . $command . "\n");
exec($command);
echo("Done.\n");
