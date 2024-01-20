<?php

// Specify the file path
$file = '/blacklist';

// Get the IP address from the POST request body
$ip = $_POST['ip'];

// Check to see if IP address is empty
if (empty($ip)) {
    echo 'ERROR: no IP address provided!';
    exit();
}

// Check to see if the file exists, and create it if it doesn't
if (!file_exists($file)) {
    touch($file);
}

// Check if the IP address is already in the file
if (strpos(file_get_contents($file), $ip) !== false) {
    echo 'SUCCESS: ' . $ip . ' already exists';
    exit();
}

// Append the IP address to the file
file_put_contents($file, $ip . PHP_EOL, FILE_APPEND);

// Send a confirmation message
echo 'SUCCESS: added ' . $ip;

?>
