<?php

include_once __DIR__ . '/helpers.php';

const BLACKLIST_TABLE = 'ip_blacklist';
const BLACKLIST_CSV = '/blacklist.csv';
const DEFAULT_BLACKLIST_DAYS = 90;

function search_blacklist(string $ip, mysqli $conn): array
{
    $sql = "
        SELECT cidr, last_seen, log_type, log_line
        FROM " . BLACKLIST_TABLE . "
        WHERE cidr = ?
            OR (
                cidr LIKE '%.%.%.%/%'
                AND INET_ATON(?) BETWEEN 
                    INET_ATON(SUBSTRING_INDEX(cidr, '/', 1)) 
                    AND 
                    INET_ATON(SUBSTRING_INDEX(cidr, '/', 1)) + POW(2, 32 - SUBSTRING_INDEX(cidr, '/', -1)) - 1
            )
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        respondError('SQL error: ' . $conn->error, 500);
        exit;
    }

    $stmt->bind_param('ss', $ip, $ip);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($conn->error) {
        respondError('SQL error: ' . $conn->error, 500);
        exit;
    }

    $blacklist = [];
    while ($row = $result->fetch_assoc()) {
        $blacklist[] = $row;
    }

    return $blacklist;
}

function write_csv(array $blacklist, string $csvFile): void
{
    $file = @fopen($csvFile, 'w');
    if ($file === false) {
        respondError('Unable to write blacklist CSV', 500);
        return;
    }

    foreach ($blacklist as $cidr) {
        fputcsv($file, [$cidr]);
    }

    fclose($file);
}

function write_yml(array $blacklist, string $ymlFile): void
{
    $file = @fopen($ymlFile, 'w');
    if ($file === false) {
        respondError('Unable to write blacklist YAML', 500);
        return;
    }

    fwrite($file, "http:\n");
    fwrite($file, "  middlewares:\n");
    fwrite($file, "    blacklist:\n");
    fwrite($file, "      plugin:\n");
    fwrite($file, "        denyip:\n");
    fwrite($file, "          ipDenyList:\n");
    foreach ($blacklist as $cidr) {
        fwrite($file, "          - $cidr\n");
    }
    fclose($file);
}

function read_sql_recent(mysqli $conn, int $days = DEFAULT_BLACKLIST_DAYS): array
{
    $sql = "SELECT cidr FROM " . BLACKLIST_TABLE . " WHERE last_seen >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        respondError('SQL error: ' . $conn->error, 500);
        exit;
    }

    $stmt->bind_param('i', $days);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($conn->error) {
        respondError('SQL error: ' . $conn->error, 500);
        exit;
    }

    $blacklist = [];
    while ($row = $result->fetch_assoc()) {
        $blacklist[] = $row['cidr'];
    }

    return $blacklist;
}
