<?php

// load environment variables
include 'blacklistutils.php';

// No caching allowed
header("Cache-Control: no-cache, no-store, must-revalidate");

// Open SQL connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':  // search for single IP address
        // Set doc type to JSON
        header('Content-Type: application/json');

        // Get the IP address from the URL
        $ip = $_GET['ip'];

        if (empty($ip)) {  // Return everything
            $blacklist = read_sql_recent($conn, $table);
            echo json_encode($blacklist);
        } else {  // Search for single IP from URL
            $blacklist = search_blacklist($ip, $conn, $table);
            echo json_encode($blacklist);
        }
        break;

    case 'POST':  // search for list of IP addresses
        // Get the ip list the POST request body
        $ips = json_decode(file_get_contents('php://input'), true);

        // Check JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle JSON parse error
            http_response_code(400); // Bad Request
            die("Invalid JSON payload");
        }

        // Go through each ip in $ips and search for it in the blacklist
        $blacklist = [];
        foreach ($ips as $ip) {
            $response = search_blacklist($ip, $conn, $table);
            if ($response) {
                $blacklist[] = $ip;
            }
        }
        echo json_encode($blacklist);

        break;

    case 'DELETE':  // delete IP address from the blacklist
        // Get the IP address from the URL
        $ip = $_GET['ip'];

        // Delete $ip from the 'blacklist' table
        $sql = "DELETE FROM $table WHERE cidr = '$ip'";
        $conn->query($sql);
        if ($conn->error) {
            $conn->close();
            die("SQL error: " . $conn->error);
        }

        // Write to files
        $blacklist = read_sql_recent($conn, $table);
        write_csv($blacklist, $csv_file);

        // Send a confirmation message
        echo $ip . ' removed from blacklist';
        break;

    default:  // Send a 405 Method Not Allowed response
        http_response_code(405);
        echo 'Method Not Allowed';
        break;
}

// Close SQL connection
$conn->close();
