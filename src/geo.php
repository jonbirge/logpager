<?php

include_once __DIR__ . '/helpers.php';

requireNoCacheHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDbConnection();

switch ($method) {
    case 'GET':
        $ipAddress = validateIpOrCidr($_GET['ip'] ?? null) ?? '8.8.8.8';
        $locArray = getGeoInfo($conn, $ipAddress);
        respondJson($locArray);
        break;

    case 'POST':
        try {
            $data = readJsonBody();
        } catch (InvalidArgumentException $e) {
            respondError($e->getMessage(), 400);
            break;
        }

        $locArray = [];
        foreach ($data as $ipAddress) {
            $ipAddress = validateIpOrCidr($ipAddress);
            if ($ipAddress === null) {
                continue;
            }

            $geoResponse = getGeoInfo($conn, $ipAddress, false);
            if ($geoResponse !== false) {
                $locArray[$ipAddress] = $geoResponse;
            }
        }

        respondJson($locArray);
        break;
    
    default:
        respondError('Unsupported HTTP method.', 405);
}

$conn->close();

function getGeoInfo(mysqli $conn, string $ipAddress, bool $failOver = true)
{
    $sql = "SELECT json_data, cache_time FROM geo_cache WHERE ip = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        respondError('SQL error: ' . $conn->error, 500);
        exit;
    }

    $stmt->bind_param('s', $ipAddress);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($conn->error) {
        respondError('SQL error: ' . $conn->error, 500);
        exit;
    }

    $isCached = false;
    $cacheTime = "";

    if ($result->num_rows === 0) {
        if ($failOver === false) {
            return false;
        }

        $locJSON = extGeoInfo($ipAddress);
        if ($locJSON !== false) {
            cacheGeoInfo($conn, $ipAddress, $locJSON);
        }
    } else {
        $row = $result->fetch_assoc();
        $locJSON = $row['json_data'];

        if ($locJSON === '' || json_decode($locJSON) === null) {
            $deleteStmt = $conn->prepare('DELETE FROM geo_cache WHERE ip = ?');
            if ($deleteStmt) {
                $deleteStmt->bind_param('s', $ipAddress);
                $deleteStmt->execute();
            }
            $locJSON = $failOver ? extGeoInfo($ipAddress) : false;
            if ($locJSON !== false) {
                cacheGeoInfo($conn, $ipAddress, $locJSON);
            }
        } else {
            $cacheTime = $row['cache_time'];
            $isCached = true;
        }
    }

    if ($locJSON === false || $locJSON === '' || json_decode($locJSON) === null) {
        return false;
    }

    $locArray = json_decode($locJSON, true);
    $locArray['cached'] = $isCached;
    $locArray['cache_error'] = 'none';
    $locArray['cache_time'] = $cacheTime;

    return $locArray;
}

function extGeoInfo(string $ipAddress)
{
    $geoURL = "http://ip-api.com/json/$ipAddress?fields=17563647";
    $locJSON = @file_get_contents($geoURL);
    if ($locJSON === false || $locJSON === "" || json_decode($locJSON) === null) {
        return false;
    }
    return $locJSON;
}

function cacheGeoInfo(mysqli $conn, string $ipAddress, string $locJSON): void
{
    $sql = "
        INSERT INTO geo_cache (ip, cache_time, json_data)
        VALUES (?, CURRENT_TIMESTAMP(), ?)
        ON DUPLICATE KEY UPDATE
            cache_time = VALUES(cache_time),
            json_data = VALUES(json_data)
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        respondError('SQL error: ' . $conn->error, 500);
        exit;
    }

    $stmt->bind_param('ss', $ipAddress, $locJSON);
    $stmt->execute();
}
