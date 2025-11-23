<?php

include_once __DIR__ . '/helpers.php';
include_once __DIR__ . '/blacklistutils.php';

requireNoCacheHeaders();

$ip = validateIpOrCidr($_POST['ip'] ?? null);
$logType = trim($_POST['log_type'] ?? '');
$logLine = $_POST['log'] ?? null;
$timestamp = trim($_POST['last_seen'] ?? '');

if ($ip === null) {
    respondError('No valid IP address or CIDR provided!', 400);
    exit;
}

if ($timestamp === '') {
    $timestamp = date('Y-m-d H:i:s');
} elseif (strtotime($timestamp) === false) {
    respondError('Invalid timestamp provided', 400);
    exit;
}

$logLine = ($logLine === null || $logLine === 'NULL' || $logLine === '') ? null : $logLine;

$conn = getDbConnection();

$sql = "
    INSERT INTO " . BLACKLIST_TABLE . " (cidr, last_seen, log_type, log_line)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        last_seen = VALUES(last_seen),
        log_type = VALUES(log_type),
        log_line = VALUES(log_line)
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    respondError('SQL error: ' . $conn->error, 500);
    $conn->close();
    exit;
}

$stmt->bind_param('ssss', $ip, $timestamp, $logType, $logLine);
$stmt->execute();

if ($conn->error) {
    respondError('SQL error: ' . $conn->error, 500);
    $conn->close();
    exit;
}

$blacklist = read_sql_recent($conn);
write_csv($blacklist, BLACKLIST_CSV);

respondJson(['message' => $ip . ' added to blacklist']);

$conn->close();
