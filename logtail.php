<table border='1'>
<tr><th>IP Address</th><th>Host Name</th><th>Date and Time</th><th>Request</th><th>Status</th><th>Size</th></tr>

<?php

// Get page parameter from URL
$page = $_GET['page'] ?? 0;

// Number of lines to read from the end of the file
$linesPerPage = 16; // You can set this to any desired integer value

// Path to the CLF log file
$logFilePath = '/access.log';

// Function to read the nth page from the end of the file
function getTailPage($filePath, $linesPerPage, $page = 0) {
    // Check if the file exists and is readable
    if (!is_readable($filePath)) {
        return "File not found or not readable.";
    }

    // Open the file for reading
    $fileHandle = fopen($filePath, 'r');
    if (!$fileHandle) {
        return "Unable to open file.";
    }

    // Seek to the end of the file
    fseek($fileHandle, 0, SEEK_END);
    $fileSize = ftell($fileHandle);
    $lines = [];
    $currentLine = '';
    $lineCount = 0;

    // Read backwards to find the starting line
    for ($pos = $fileSize - 2; $pos >= 0; $pos--) {
        fseek($fileHandle, $pos);
        $char = fgetc($fileHandle);

        if ($char === "\n") {
            // Start capturing lines after reaching the required page
            if (++$lineCount >= $linesPerPage * ($page + 1)) {
                break;
            }
            if ($lineCount > $linesPerPage * $page) {
                array_unshift($lines, $currentLine);
                $currentLine = '';
            }
        } elseif ($pos > 0) {
            $currentLine = $char . $currentLine;
        }
    }

    // Add the last line if the file does not end with a newline
    if ($lineCount > $linesPerPage * $page && $currentLine !== '') {
        array_unshift($lines, $currentLine);
    }

    fclose($fileHandle);
    return $lines;
}

// Read the last n lines from the file
$lastPageLines = getTailPage($logFilePath, $linesPerPage, $page);

// Process each line
foreach ($lastPageLines as $line) {
    preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) (\S+)/', $line, $matches);

    if (!empty($matches)) {
        $ip = $matches[1];
        $host = gethostbyaddr($ip); // Lookup the host name from the IP address

        echo "<tr>";
        echo "<td>" . htmlspecialchars($ip) . "</td>";
        echo "<td>" . htmlspecialchars($host) . "</td>";
        for ($i = 2; $i <= 5; $i++) {
            echo "<td>" . htmlspecialchars($matches[$i] ?? '-') . "</td>";
        }
        echo "</tr>";
    }
}

?>

</table>
