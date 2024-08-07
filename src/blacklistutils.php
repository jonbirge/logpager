<?php

// ***** Environment Variables *****

$host = getenv('SQL_HOST');
$user = getenv('SQL_USER');
$pass = getenv('SQL_PASS');
$db = getenv('SQL_DB');
$table = 'ip_blacklist';
$csv_file = '/blacklist.csv';


// ***** Utility Functions *****

function search_blacklist($ip, $conn, $table) {
    // SQL query
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

    return $blacklist;
}

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
