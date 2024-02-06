<?php
$user_ip = $_GET['ip'] ?? '';
$uniqueId = $_GET['id'] ?? '';
$command = "./runscan.sh " . escapeshellarg($uniqueId) . " " . escapeshellarg($user_ip) . " > /dev/null 2>&1 &";
echo("Starting server command: " . $command . "\n");
exec($command);
echo("Done.\n");
