<?php

include_once __DIR__ . '/helpers.php';
include_once __DIR__ . '/blacklistutils.php';

requireNoCacheHeaders();

$conn = getDbConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $ip = validateIpOrCidr($_GET['ip'] ?? null);

        if ($ip === null) {
            $blacklist = read_sql_recent($conn);
            respondJson($blacklist);
        } else {
            $blacklist = search_blacklist($ip, $conn);
            respondJson($blacklist);
        }
        break;

    case 'POST':
        try {
            $ips = readJsonBody();
        } catch (InvalidArgumentException $e) {
            respondError($e->getMessage(), 400);
            break;
        }

        $blacklist = [];
        foreach ($ips as $rawIp) {
            $ip = validateIpOrCidr($rawIp);
            if ($ip === null) {
                continue;
            }
            $response = search_blacklist($ip, $conn);
            if (!empty($response)) {
                $blacklist[] = $ip;
            }
        }
        respondJson($blacklist);

        break;

    case 'DELETE':
        $ip = validateIpOrCidr($_GET['ip'] ?? null);
        if ($ip === null) {
            respondError('No valid IP address or CIDR provided!', 400);
            break;
        }

        $sql = "DELETE FROM " . BLACKLIST_TABLE . " WHERE cidr = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            respondError('SQL error: ' . $conn->error, 500);
            break;
        }

        $stmt->bind_param('s', $ip);
        $stmt->execute();

        if ($conn->error) {
            respondError('SQL error: ' . $conn->error, 500);
            break;
        }

        $blacklist = read_sql_recent($conn);
        write_csv($blacklist, BLACKLIST_CSV);

        respondJson(['message' => $ip . ' removed from blacklist']);
        break;

    default:
        respondError('Method Not Allowed', 405);
        break;
}

$conn->close();
