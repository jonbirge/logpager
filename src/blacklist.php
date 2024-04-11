<?php

// Create array with all host environment variables
$host = getenv('SQL_HOST');
$user = getenv('SQL_USER');
$pass = getenv('SQL_PASS');
$db = getenv('SQL_DB');
$table = 'ip_blacklist';

// No caching allowed
header("Cache-Control: no-cache, no-store, must-revalidate");

// Open SQL connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all rows from the 'blacklist' table
    $sql = "SELECT * FROM $table";
    $result = $conn->query($sql);
    if ($conn->error) {
        die("SQL error: " . $conn->error);
    }

    // Loop through each row in the 'blacklist' table and add the 'cidr' column to the array
    $blacklist = [];
    while ($row = $result->fetch_assoc()) {
        $blacklist[] = $row['cidr'];
    }

    // Send the array as a JSON response
    echo json_encode($blacklist);

} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the IP address from the POST request body
    $ip = $_POST['ip'];

    // Get the optional data from the POST request body
    $log_type = $_POST['log_type'];
    $log = $_POST['log'];

    // Check to see if IP address is empty
    if (empty($ip)) {
        echo 'no IP address provided!';
        exit();
    }

    // Insert $ip and $log (if exists) into the 'blacklist' table
    $sql = "INSERT INTO $table (cidr, log_type, log_line) VALUES ('$ip', '$log_type', '$log')";
    $conn->query($sql);
    if ($conn->error) {
        die("SQL error: " . $conn->error);
    }

    // Send a confirmation message
    echo $ip . ' added to blacklist';

} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the IP address from the URL
    $ip = $_GET['ip'];

    // Delete $ip from the 'blacklist' table
    $sql = "DELETE FROM $table WHERE cidr = '$ip'";
    $conn->query($sql);
    if ($conn->error) {
        die("SQL error: " . $conn->error);
    }

    // Send a confirmation message
    echo $ip . ' removed from blacklist';

} else {
    // Send a 405 Method Not Allowed response
    http_response_code(405);
    echo 'Method Not Allowed';
}

// Close SQL connection
$conn->close();
