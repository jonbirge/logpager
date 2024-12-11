<?php

// load environment variables
include 'blacklistutils.php';

// Get the data from the POST request body
$ip = $_POST['ip'];
$log_type = $_POST['log_type'];
$log = $_POST['log'];
$timestamp = $_POST['last_seen'];

// Check to see if IP address is empty
if (empty($ip)) {
    die("no IP address provided!");
}

// Open SQL connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Insert $ip and $log (if exists) into the 'blacklist' table
if ($log === 'NULL') {
    $sql = "INSERT INTO $table (cidr, last_seen, log_type) VALUES ('$ip', '$timestamp', '$log_type')";
} else {
    $sql = "INSERT INTO $table (cidr, last_seen, log_type, log_line) VALUES ('$ip', '$timestamp', '$log_type', '$log')";
}

try {
    $conn->query($sql);
    if ($conn->error) {
        throw new Exception("SQL error: " . $conn->error);
    }
} catch (Exception $e) {
    $conn->close();
    echo $e->getMessage();
    die($e->getMessage());
}

// Update CSV file
$blacklist = read_sql_recent($conn, $table);
write_csv($blacklist, $csv_file);

echo $ip . ' added to blacklist';

// Close SQL connection
$conn->close();
