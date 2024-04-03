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

// Check if the IP address is in the database
$isCached = false;
$cacheError = "none";
$cacheTime = "";
if ($result->num_rows == 0) {
    // Send ip address to ip-api.com geolocation API
    $locJSON = file_get_contents("http://ip-api.com/json/$ipAddress?fields=17563647");

    // If the API returns an error, don't cache
    if ($locJSON == false) {
        die("Error: IP-API returned jack.");
    }

    // Insert the IP address and the geolocation data into the database
    $sql = "INSERT INTO geo (ip, cache_time, json_data) VALUES ('$ipAddress', CURRENT_TIMESTAMP(), '$locJSON')";

    // Execute the SQL query, catching any errors using try/catch block
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        $cacheError = $e->getMessage();
    }
} else {
    // If the IP address is in the database, return the geolocation data
    $row = $result->fetch_assoc();
    $locJSON = $row['json_data'];
    $cacheTime = $row['cache_time'];

    $isCached = true;
}

// Add the isCached and cacheError variables to the JSON response
$locArray = json_decode($locJSON, true);
$locArray['cached'] = $isCached;
$locArray['cache_error'] = $cacheError;
$locArray['cache_time'] = $cacheTime;
$locJSON = json_encode($locArray);

// Return answer
echo $locJSON;
