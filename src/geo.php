<?php

// Get SQL server parameters from environment
$host = getenv('SQL_HOST');
$user = getenv('SQL_USER');
$pass = getenv('SQL_PASS');
$db = getenv('SQL_DB');

// Allow very long caching
header("Cache-Control: max-age=86400, must-revalidate");

// Get parameters from URL
$ipAddress = $_GET['ip'];

// Open SQL connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "<p>Connected to SQL server</p>";
}

// Prepare SQL query
$sql = "SELECT * FROM geo WHERE ip = '$ipAddress'";
$result = $conn->query($sql);
if ($conn->error) {
    echo "SQL error: " . $conn->error;
    die("SQL error: " . $conn->error);
} else {
    echo "<p>SQL query executed</p>";
}

// If the IP address is not in the database
if ($result->num_rows == 0) {
    echo "<p>IP address not found in database</p>";

    // Send ip address to ip-api.com geolocation API
    $locJSON = file_get_contents("http://ip-api.com/json/$ipAddress?fields=17563647");

    // Insert the IP address and the geolocation data into the database
    $sql = "INSERT INTO geo (ip, data) VALUES ('$ipAddress', '$locJSON')";
    $conn->query($sql);
} else {
    echo "<p>IP address found in database</p>";

    // If the IP address is in the database, return the geolocation data
    $row = $result->fetch_assoc();
    $locJSON = $row['data'];
}

// Return answer
echo $locJSON;
