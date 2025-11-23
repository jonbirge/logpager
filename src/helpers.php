<?php

function requireNoCacheHeaders()
{
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function respondJson($data, int $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
}

function respondError(string $message, int $statusCode = 400)
{
    respondJson(['error' => $message], $statusCode);
}

function getDbConnection(): mysqli
{
    $host = getenv('SQL_HOST');
    $user = getenv('SQL_USER');
    $pass = getenv('SQL_PASS');
    $db = getenv('SQL_DB');

    $conn = @new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        respondError('Connection failed: ' . $conn->connect_error, 500);
        exit;
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('Invalid JSON payload');
    }

    return $data;
}

function validateIpOrCidr(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (filter_var($value, FILTER_VALIDATE_IP)) {
        return $value;
    }

    if (preg_match('/^(\d{1,3}\.){3}\d{1,3}\/([0-9]|[1-2][0-9]|3[0-2])$/', $value)) {
        return $value;
    }

    return null;
}
