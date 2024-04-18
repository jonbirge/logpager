<?php

// Create array with all host environment variables
$host = getenv('SQL_HOST');
$user = getenv('SQL_USER');
$pass = getenv('SQL_PASS');
$db = getenv('SQL_DB');

// No caching allowed
// header("Cache-Control: max-age=86400, must-revalidate");
header("Cache-Control: no-cache, no-store, must-revalidate");

// Get HTML request method
$method = $_SERVER['REQUEST_METHOD'];

// Open SQL connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Use case to handle GET versus POST requests
switch ($method) {
    case 'GET':
        // Get parameters from URL
        $ipAddress = $_GET['ip'];
        
        // If the IP address is not provided, use a dummy test address
        if ($ipAddress == "") {
            $ipAddress = "8.8.8.8";
        }

        // Pull data from cache or external service
        $locArray = getGeoInfo($conn, $ipAddress);
        $locJSON = json_encode($locArray);
        echo $locJSON;

        break;

    case 'POST':
        echo "POST not yet implemented.";
        
        break;
    
    default:
        echo "Unsupported HTTP method.";
}

function getGeoInfo($conn, $ipAddress)
{
    // Prepare SQL query
    $sql = "SELECT * FROM geo_cache WHERE ip = '$ipAddress'";
    $result = $conn->query($sql);
    if ($conn->error) {
        die("SQL error: " . $conn->error);
    }

    // Check if the IP address is in the database
    $isCached = false;
    $cacheError = "none";
    $cacheTime = "";
    if ($result->num_rows == 0) {
        // If the IP address is not in the database, get the geolocation data from the external service
        $locJSON = extGeoInfo($ipAddress);

        // If the geolocation data is not valid JSON, return nothing
        if (json_decode($locJSON) == null) {
            $locJSON = "";
        } else {
            // Cache the geolocation data
            cacheGeoInfo($conn, $ipAddress, $locJSON);
        }
    } else {
        // If the IP address is in the database, get the geolocation data from the database
        $row = $result->fetch_assoc();
        $locJSON = $row['json_data'];

        // If cached data is bad, clear the cache and load from service (again)
        if ($locJSON == false || $locJSON == "" || json_decode($locJSON) == null) {
            $sql = "DELETE FROM geo WHERE ip = '$ipAddress'";
            $conn->query($sql);
            $locJSON = extGeoInfo($ipAddress);
            cacheGeoInfo($conn, $ipAddress, $locJSON);
            $isCached = false;
        } else {
            $cacheTime = $row['cache_time'];
            $isCached = true;
        }
    }

    // Add the isCached and cacheError variables to the JSON response
    $locArray = json_decode($locJSON, true);
    $locArray['cached'] = $isCached;
    $locArray['cache_error'] = $cacheError;
    $locArray['cache_time'] = $cacheTime;

    return $locArray;
}

// Function to pull geo information from external web API
function extGeoInfo($ipAddress)
{
    $geoURL = "http://ip-api.com/json/$ipAddress?fields=17563647";
    $locJSON = file_get_contents($geoURL);
    if ($locJSON == "" || json_decode($locJSON) == null) {
        return false;
    }
    return $locJSON;
}

// Function to cache the geo information given a database connection
function cacheGeoInfo($conn, $ipAddress, $locJSON)
{
    // Sanitize the JSON data for inclusion in the SQL query
    $locJSON = $conn->real_escape_string($locJSON);

    // Insert the IP address and the geolocation data into the database
    $sql = "INSERT INTO geo_cache (ip, cache_time, json_data) VALUES ('$ipAddress', CURRENT_TIMESTAMP(), '$locJSON')";

    try {
        $conn->query($sql);
    } catch (Exception $e) {
        $cacheError = $e->getMessage();
    }
}
