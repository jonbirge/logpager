<?php

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

    // Send a confirmation message
    echo $ip . ' added to blacklist';
} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the IP address from the URL
    $ip = $_GET['ip'];

    // Remove the IP address from the file if it exists
    $contents = file_get_contents($file);
    $contents = str_replace($ip . PHP_EOL, '', $contents);
    file_put_contents($file, $contents);

    // Send a confirmation message
    echo $ip . ' removed from blacklist';
} else {
    // Send a 405 Method Not Allowed response
    http_response_code(405);
    echo 'Method Not Allowed';
}
