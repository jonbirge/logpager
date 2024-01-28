<?php

$uniqueId = $_GET['id'] ?? '';
$tempFile = "/tmp/trace_output_" . $uniqueId . ".txt";
$lockFile = "/tmp/trace_output_" . $uniqueId . ".lock";

// Check for the existence of the lock file
if (!file_exists($lockFile)) {
    echo "<!-- END_OF_FILE -->";
}

// Check if the text file exists
if (file_exists($tempFile)) {
    // Open the file for reading
    $handle = fopen($tempFile, "r");

    // Start the HTML table
    echo "<table>";

    // Read each line of the text file
    while (!feof($handle)) {
        $line = trim(fgets($handle));

        // Skip empty lines
        if ($line != '') {
            echo "<tr>";

            // Pull out a leading number and the rest of the line
            $lineParts = explode(' ', $line, 2);
            $lineNumber = $lineParts[0];
            $lineText = $lineParts[1];

            // If the line ends with "ms" then pull out the ping time from the end of the line
            if (substr($lineText, -2) == 'ms') {
                $lineParts = explode(' ', $lineText);
                $pingTime = $lineParts[count($lineParts) - 2] . ' ' . $lineParts[count($lineParts) - 1];
                $lineText = substr($lineText, 0, -strlen($pingTime));
            } else {
                $pingTime = '-';
            }

            echo "<td>" . $lineNumber . "</td>";
            echo "<td>" . $lineText . "</td>";
            echo "<td>" . $pingTime . "</td>";

            echo "</tr>";
        }
    }

    // Close the HTML table
    echo "</table>";

    // Close the file handle
    fclose($handle);
}
