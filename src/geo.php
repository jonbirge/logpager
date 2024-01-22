<?php

// Cache control
header("Cache-Control: max-age=86400, must-revalidate");

// Get parameters from URL
$ipAddress = $_GET['ip'];

// Send ip address to ip-api.com geolocation API
$locJSON = file_get_contents("http://ip-api.com/json/$ipAddress");

// Return answer
echo $locJSON;

?>
