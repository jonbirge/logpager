<?php

include 'traefik.php';

function countFileLines($filePath)
{
    $lineCount = 0;
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        return 0;
    }

    while (!feof($handle)) {
        $chunk = fread($handle, 8192);
        if ($chunk === false) {
            break;
        }
        $lineCount += substr_count($chunk, "\n");
    }

    fclose($handle);
    return $lineCount;
}

function readSectionFromFile($filePath, $startLine, $endLine)
{
    $lines = [];
    $file = new SplFileObject($filePath, 'r');
    $file->seek($startLine);

    for ($lineNumber = $startLine; $lineNumber < $endLine && !$file->eof(); $lineNumber++) {
        $line = $file->current();
        if ($line !== false) {
            $lines[] = $line;
        }
        $file->next();
    }

    return $lines;
}

function tail($page, $linesPerPage)
{
    // Retrieve log file paths using getTraefikLogFiles()
    $logFilePaths = getTraefikLogFiles();

    // Reverse the array to get oldest files first for chronological order
    $logFilePaths = array_reverse($logFilePaths);

    // Build line counts per file without loading contents into memory
    $lineCounts = [];
    $totalLines = 0;
    foreach ($logFilePaths as $filePath) {
        $count = countFileLines($filePath);
        $lineCounts[] = $count;
        $totalLines += $count;
    }

    // Calculate total lines and number of pages
    $pageCount = ceil($totalLines / $linesPerPage);

    // Calculate the page number we'll actually be returning
    $page = min($page, max(0, $pageCount - 1));

    // Determine the range of lines to read
    $startLine = max(0, $totalLines - ($page + 1) * $linesPerPage);
    $endLine = min($totalLines, $totalLines - $page * $linesPerPage);

    // Extract the lines for the requested page without loading full files
    $lines = [];
    $lineOffset = 0;
    foreach ($logFilePaths as $index => $filePath) {
        $fileLineCount = $lineCounts[$index];
        $fileStart = $lineOffset;
        $fileEnd = $lineOffset + $fileLineCount;

        // Skip files that fall completely outside the requested range
        if ($endLine <= $fileStart || $startLine >= $fileEnd) {
            $lineOffset = $fileEnd;
            continue;
        }

        // Determine slice relative to current file
        $relativeStart = max(0, $startLine - $fileStart);
        $relativeEnd = min($fileLineCount, $endLine - $fileStart);

        if ($relativeStart < $relativeEnd) {
            $lines = array_merge(
                $lines,
                readSectionFromFile($filePath, $relativeStart, $relativeEnd)
            );
        }

        $lineOffset = $fileEnd;
    }

    // Read in CLF header name array from loghead.json
    $headers = json_decode(file_get_contents('traefik/loghead.json'));

    // Create array of CLF log lines
    $logLines = [];

    // Process each line and add to the array
    foreach ($lines as $line) {
        // Extract the important CLF and Traefik special fields from the line
        $matched = preg_match('/(\S+) \S+ \S+ \[(.+?)\] \"(.*?)\" (\S+) \S+ \"-\" \"-\" \S+ \"(\S+)\" \"\S+\" \S+/', $line, $matches);
        if ($matched !== 1) {
            continue; // skip malformed lines
        }

        // swap the last two matches so the status is always last
        $temp = $matches[4];
        $matches[4] = $matches[5];
        $matches[5] = $temp;

        // Go through each match and add to the array with htmlspecialchars()
        $logLines[] = array_slice($matches, 1);
    }
    $logLines[] = $headers;

    // Output $logLines, $page and $lineCount as a JSON dictionary
    echo json_encode([
        'page' => $page,
        'pageCount' => $pageCount,
        'lineCount' => $totalLines,
        'logLines' => array_reverse($logLines)
    ]);
}
