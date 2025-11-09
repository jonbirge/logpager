<?php

// Include the authparse.php file
include 'authparse.php';

function tail($page, $linesPerPage)
{
    // Current year
    $year = date('Y');

    // path to the auth log files
    $logFilePaths = getAuthLogFiles();

    // Reverse the array to get oldest files first for chronological order
    $logFilePaths = array_reverse($logFilePaths);

    $allLines = [];

    // Concatenate all log files into an array in memory
    foreach ($logFilePaths as $filePath) {
        $file = new SplFileObject($filePath, 'r');
        while (!$file->eof()) {
            $line = $file->fgets();
            if (preg_match('/([0-9]{1,3}\.){3}[0-9]{1,3}/', $line)) {
                $allLines[] = $line;
            }
        }
    }

    // Reverse the array to process the most recent lines first
    $allLines = array_reverse($allLines);
    $lineCount = count($allLines);

    // Calculate the number of pages
    $pageCount = ceil($lineCount / $linesPerPage);
    $page = min($page, $pageCount - 1); // Ensure the page is within bounds

    // Get the lines for the requested page
    $start = $linesPerPage * $page;
    $pageLines = array_slice($allLines, $start, $linesPerPage);

    // Read in CLF header name array from clfhead.json
    $headers = json_decode(file_get_contents('auth/loghead.json'));

    // Create array of auth log lines
    $logLines = [];
    $logLines[] = $headers;

    // Process each line and add to the array
    foreach ($pageLines as $line) {
        // parse log line
        $data = parseAuthLogLine($line, $year);

        // determine status based on $data[2]
        $status = getAuthLogStatus($data[2]);

        $logLines[] = [$data[0], $data[1], $data[2], $status];
    }

    // Output $logLines, $page and $lineCount as a JSON dictionary
    echo json_encode([
        'page' => $page,
        'pageCount' => $pageCount,
        'lineCount' => $lineCount,
        'logLines' => $logLines
    ]);
}
