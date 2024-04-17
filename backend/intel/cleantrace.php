<?php
$uniqueId = $_GET['id'] ?? '';
$command = "rm /tmp/trace_output_" . $uniqueId . ".txt";
echo("Starting cleanup: " . $command . "\n");
exec($command);
echo("Done.\n");
