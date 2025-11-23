<?php

// Include the authparse.php file
include 'authparse.php';

function countMatchingLines($filePath, $pattern)
{
    $count = 0;
    $file = new SplFileObject($filePath, 'r');
    while (!$file->eof()) {
        $line = $file->fgets();
        if (preg_match($pattern, $line)) {
            $count++;
        }
    }
    return $count;
}

function readMatchingSection($filePath, $pattern, $startLine, $endLine)
{
    $lines = [];
    $file = new SplFileObject($filePath, 'r');
    $matchIndex = 0;

    while (!$file->eof() && $matchIndex < $endLine) {
        $line = $file->fgets();
        if (preg_match($pattern, $line)) {
            if ($matchIndex >= $startLine) {
                $lines[] = $line;
            }
            $matchIndex++;
        }
    }

    return $lines;
}

function tail($page, $linesPerPage)
{
    // Current year
    $year = date('Y');

    // path to the auth log files
    $logFilePaths = getAuthLogFiles();

    // Reverse the array to get oldest files first for chronological order
    $logFilePaths = array_reverse($logFilePaths);

    $ipPattern = '/([0-9]{1,3}\.){3}[0-9]{1,3}/';

    // Count matching lines per file without loading everything into memory
    $lineCounts = [];
    $lineCount = 0;
    foreach ($logFilePaths as $filePath) {
        $count = countMatchingLines($filePath, $ipPattern);
        $lineCounts[] = $count;
        $lineCount += $count;
    }

    // Calculate the number of pages
    $pageCount = $lineCount > 0 ? ceil($lineCount / $linesPerPage) : 0;
    $page = min($page, max(0, $pageCount - 1)); // Ensure the page is within bounds

    // Determine the range of lines to read (chronological order)
    $startLine = max(0, $lineCount - ($page + 1) * $linesPerPage);
    $endLine = min($lineCount, $lineCount - $page * $linesPerPage);

    // Read only the required lines from each file
    $pageLines = [];
    $lineOffset = 0;
    foreach ($logFilePaths as $index => $filePath) {
        $fileLineCount = $lineCounts[$index];
        $fileStart = $lineOffset;
        $fileEnd = $lineOffset + $fileLineCount;

        // Skip files outside the requested range
        if ($endLine <= $fileStart || $startLine >= $fileEnd) {
            $lineOffset = $fileEnd;
            continue;
        }

        $relativeStart = max(0, $startLine - $fileStart);
        $relativeEnd = min($fileLineCount, $endLine - $fileStart);

        if ($relativeStart < $relativeEnd) {
            $pageLines = array_merge(
                $pageLines,
                readMatchingSection($filePath, $ipPattern, $relativeStart, $relativeEnd)
            );
        }

        $lineOffset = $fileEnd;
    }

    // Reverse to present most recent lines first
    $pageLines = array_reverse($pageLines);

    // Read in CLF header name array from clfhead.json
    $headers = json_decode(file_get_contents('auth/loghead.json'));

    // Create array of auth log lines
    $logLines = [];
    $logLines[] = $headers;

    // Process each line and add to the array
    foreach ($pageLines as $line) {
        // parse log line
        $data = parseAuthLogLine($line, $year);
        if ($data === false) {
            continue; // skip malformed lines
        }

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
