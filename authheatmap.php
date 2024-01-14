<?php

// Include the authparse.php file
include 'authparse.php';

// Get an optional 'ip' query string parameter
$searchTerm = $_GET['search'] ?? null;

// Log file to read
$logFilePath = '/auth.log';

// generate UNIX grep command line argument to only include lines containing IP addresses
$escFilePath = escapeshellarg($logFilePath);
$grepIPCmd = "grep -E '([0-9]{1,3}\.){3}[0-9]{1,3}' $escFilePath";

// generate UNIX grep command line arguments to include services we care about
$services = ['sshd', 'sudo'];
$grepArgs = '';
foreach ($services as $service) {
    $grepArgs .= " -e $service";
}
$escFilePath = escapeshellarg($logFilePath);
$grepSrvCmd = "grep $grepArgs $escFilePath";

// build UNIX command to get the last $linesPerPage lines
$cmd = "$grepSrvCmd | $grepIPCmd";

// execute the UNIX command
$fp = popen($cmd, 'r');

// Hour integer to string conversion function
function hourStr($hour) {
    if ($hour < 10) {
        return "0$hour";
    } else {
        return "$hour";
    }
}

// Initialize an empty array to store the log summary data
$logSummary = [];

// Read each line of the log file
while (($line = fgets($fp)) !== false) {
    // Skip this log entry if the search term isn't found in $line
    if ($searchTerm !== null && strpos($line, $searchTerm) === false) {
        continue;
    }

    $status = getAuthLogStatus($line);

    if ($status !== 'FAIL') {
        continue;
    }

    $data = parseAuthLogLine($line);

    // Extract the timestamp from the auth log entry
    $timeStamp = $data[1];

    // Convert the timestamp to a DateTime object
    $date = DateTime::createFromFormat('d/M/Y:H:i:s', $timeStamp);

    // Check if the DateTime object was created successfully
    if ($date !== false) {
        // Get the date in the format YYYY-MM-DD
        $dayOfYear = $date->format('Y-m-d');
        // Get the hour of the day
        $hour = $date->format('G'); // 24-hour format without leading zeros
    } else {
        echo "<p>Invalid timestamp format encountered: $timeStamp</p>";
        return;
    }

    // Initialize the count for the day of the year and hour of the day
    $hStr = hourStr($hour);
    if (!isset($logSummary[$dayOfYear][$hStr])) {
        $logSummary[$dayOfYear][$hStr] = 0;
    }
    // Increment the count for the day of the year and hour of the day
    $logSummary[$dayOfYear][$hStr]++;
}

pclose($fp);

// Echo the log summary data as JSON
echo json_encode($logSummary);

?>
