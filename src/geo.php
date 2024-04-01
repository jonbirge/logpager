<?php

// Create array with all host environment variables
$host = getenv('SQL_HOST');
$user = getenv('SQL_USER');
$pass = getenv('SQL_PASS');
$db = getenv('SQL_DB');

// Allow very long caching
// header("Cache-Control: max-age=86400, must-revalidate");

// Get parameters from URL
$ipAddress = $_GET['ip'];
// If the IP address is not provided, use a dummy test address
if ($ipAddress == "") {
    $ipAddress = "173.48.140.140";
}

// Open SQL connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Prepare SQL query
$sql = "SELECT * FROM geo WHERE ip = '$ipAddress'";
$result = $conn->query($sql);
if ($conn->error) {
    die("SQL error: " . $conn->error);
}

// If the IP address is not in the database
if ($result->num_rows == 0) {
    // Send ip address to ip-api.com geolocation API
    $locJSON = file_get_contents("http://ip-api.com/json/$ipAddress?fields=17563647");

    // Insert the IP address and the geolocation data into the database
    $sql = "INSERT INTO geo (ip, data) VALUES ('$ipAddress', '$locJSON')";
    $conn->query($sql);

    // Add field to JSON to indicate that the data is NOT cached
    $locJSON = json_decode($locJSON, true);
    $locJSON['cached'] = false;
    $locJSON = json_encode($locJSON);
} else {
    // If the IP address is in the database, return the geolocation data
    $row = $result->fetch_assoc();
    $locJSON = $row['data'];

    // Add field to JSON to indicate that the data is cached
    $locJSON = json_decode($locJSON, true);
    $locJSON['cached'] = true;
    $locJSON = json_encode($locJSON);
}

// Return answer
echo $locJSON;
