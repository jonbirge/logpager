<?php

// Create array with all host environment variables
$host = getenv('SQL_HOST');
$user = getenv('SQL_USER');
$pass = getenv('SQL_PASS');
$db = getenv('SQL_DB');
$table = 'ip_blacklist';
$csv_file = '/blacklist.csv';

// No caching allowed
header("Cache-Control: no-cache, no-store, must-revalidate");

// Open SQL connection
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle HTTP methods
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Set doc type to JSON
    header('Content-Type: application/json');

    // Get the IP address from the URL
    $ip = $_GET['ip'];

    if (empty($ip)) { // Send the entire database if no IP address given
        $blacklist = read_sql_recent($conn, $table);
        echo json_encode($blacklist);
    } else { // Check if the IP address exists in the database, either directly or contained in a CIDR block
        $sql = "
            SELECT * FROM $table 
            WHERE 
                cidr = ? 
                OR (
                    cidr LIKE '%.%.%.%/%' AND
                    INET_ATON(?) BETWEEN 
                    INET_ATON(SUBSTRING_INDEX(cidr, '/', 1)) 
                    AND 
                    INET_ATON(SUBSTRING_INDEX(cidr, '/', 1)) + POW(2, 32 - SUBSTRING_INDEX(cidr, '/', -1)) - 1
                )
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $ip, $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($conn->error) {
            die("SQL error: " . $conn->error);
        }
        
        // create an array of the rows returned
        $blacklist = [];
        while ($row = $result->fetch_assoc()) {
            $blacklist[] = $row;
        }

        echo json_encode($blacklist);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the IP address from the POST request body
    $ip = $_POST['ip'];
    
    // Get the optional data from the POST request body
    $log_type = $_POST['log_type'];
    $log = $_POST['log'];
    $timestamp = $_POST['last_seen'];
    
    // Check to see if IP address is empty
    if (empty($ip)) {
        die("no IP address provided!");
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

    // Write to CSV file
    $blacklist = read_sql_recent($conn, $table);
    write_csv($blacklist, $csv_file);

    echo $ip . ' added to blacklist';

} else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
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

} else {  // Send a 405 Method Not Allowed response
    http_response_code(405);
    echo 'Method Not Allowed';
}

// Close SQL connection
$conn->close();


// function to write all blacklist IPs/CIDRs to a csv file
function write_csv($blacklist, $csv_file) {
    $file = fopen($csv_file, 'w');
    foreach ($blacklist as $cidr) {
        fputcsv($file, [$cidr]);
    }
    fclose($file);
}

// function to write all ip/cidr values to a yml file suitable for use with traefik's denyip plugin middleware with the following examples format:
function write_yml($blacklist, $yml_file) {
    $file = fopen($yml_file, 'w');
    fwrite($file, "http:\n");
    fwrite($file, "  middlewares:\n");
    fwrite($file, "    blacklist:\n");
    fwrite($file, "      plugin:\n");
    fwrite($file, "        denyip:\n");
    fwrite($file, "          ipDenyList:\n");
    foreach ($blacklist as $cidr) {
        fwrite($file, "          - $cidr\n");
    }
    fclose($file);
}

// function to read all cidr values from SQL into array
function read_sql_recent($conn, $table, $days = 90) {
    $sql = "SELECT * FROM $table WHERE last_seen >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($conn->error) {
        die("SQL error: " . $conn->error);
    }
    
    $blacklist = [];
    while ($row = $result->fetch_assoc()) {
        $blacklist[] = $row['cidr'];
    }

    return $blacklist;
}
