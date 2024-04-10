<?php

// Create array with all host environment variables
$host = getenv('SQL_HOST');
$user = getenv('SQL_USER');
$pass = getenv('SQL_PASS');
$db = getenv('SQL_DB');

// No caching allowed
// header("Cache-Control: max-age=86400, must-revalidate");
header("Cache-Control: no-cache, no-store, must-revalidate");

// Specify the file path
$file = '/blacklist';

// Check to see if the file exists, and create it if it doesn't
if (!file_exists($file)) {
    touch($file);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // read file contents into an array
    $blacklist = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // Send the array as a JSON response
    echo json_encode($blacklist);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the IP address from the POST request body
    $ip = $_POST['ip'];

    // Get the log line from the POST request body if it exists
    $log = $_POST['log'];

    // If the log line is empty, set it to 'N/A'
    // if (empty($log)) {
    //     $log = 'N/A';
    // }

    // Check to see if IP address is empty
    if (empty($ip)) {
        echo 'no IP address provided!';
        exit();
    }

    // Check if the IP address is already in the file
    if (strpos(file_get_contents($file), $ip) !== false) {
        echo $ip . ' already exists in blacklist';
        exit();
    }

    // Append the IP address to the file
    file_put_contents($file, $ip . PHP_EOL, FILE_APPEND);

    // Open SQL connection
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Insert $ip and $log (if exists) into the 'blacklist' table
    $sql = "INSERT INTO blacklist (cidr, log_line) VALUES ('$ip', '$log')";
    $conn->query($sql);
    if ($conn->error) {
        die("SQL error: " . $conn->error);
    }

    // Close SQL connection
    $conn->close();

    // Send a confirmation message
    echo $ip . ' added to blacklist';
} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the IP address from the URL
    $ip = $_GET['ip'];

    // Remove the IP address from the file if it exists
    $contents = file_get_contents($file);
    $contents = str_replace($ip . PHP_EOL, '', $contents);
    file_put_contents($file, $contents);

    // Open SQL connection
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Delete $ip from the 'blacklist' table
    $sql = "DELETE FROM blacklist WHERE cidr = '$ip'";
    $conn->query($sql);
    if ($conn->error) {
        die("SQL error: " . $conn->error);
    }

    // Close SQL connection
    $conn->close();

    // Send a confirmation message
    echo $ip . ' removed from blacklist';
} else {
    // Send a 405 Method Not Allowed response
    http_response_code(405);
    echo 'Method Not Allowed';
}
