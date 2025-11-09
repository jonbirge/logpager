<?php
declare(strict_types=1);

$host = getenv('SQL_HOST') ?: 'localhost';
$user = getenv('SQL_USER') ?: 'root';
$pass = getenv('SQL_PASS') ?: '';
$database = getenv('SQL_DB') ?: 'logpager';
$port = (int) (getenv('SQL_PORT') ?: 3306);
$retrySeconds = (int) (getenv('SQL_RETRY_SECONDS') ?: 5);
$sqlFile = '/db.sql';

function log_msg(string $message): void
{
    echo $message . PHP_EOL;
}

function wait_for_sql(string $host, int $port, string $user, string $pass, int $retrySeconds): mysqli
{
    while (true) {
        $mysqli = mysqli_init();
        if (@$mysqli->real_connect($host, $user, $pass, null, $port)) {
            return $mysqli;
        }

        $error = mysqli_connect_error() ?: 'Unknown error';
        log_msg(sprintf('Waiting for SQL... (%s)', $error));
        sleep(max(1, $retrySeconds));
    }
}

if (!file_exists($sqlFile)) {
    fwrite(STDERR, sprintf('SQL bootstrap file not found at %s', $sqlFile) . PHP_EOL);
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, 'SQL bootstrap file is empty or unreadable.' . PHP_EOL);
    exit(1);
}

$mysqli = wait_for_sql($host, $port, $user, $pass, $retrySeconds);
log_msg('Creating SQL database and tables, if needed...');

if ($mysqli->multi_query($sql) === false) {
    fwrite(STDERR, sprintf('Failed to run bootstrap SQL: %s', $mysqli->error) . PHP_EOL);
    exit(1);
}

while ($mysqli->more_results()) {
    $mysqli->next_result();
}

log_msg('SQL is ready. Continuing...');
$mysqli->close();
