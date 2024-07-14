<?php

// Include the authparse.php file
include 'authparse.php';

function tail($page, $linesPerPage)
{
    // path to the auth log file
    $logFilePaths = getAuthLogFiles();

    // create random temporary file path
    $tmpFilePath = '/tmp/authlog-' . bin2hex(random_int(0, PHP_INT_MAX)) . '.log';

    // generate UNIX grep command line argument to only include lines containing IP addresses
    $grepIPCmd = "grep -E '([0-9]{1,3}\.){3}[0-9]{1,3}'";

    // generate UNIX grep command line arguments to include services we care about
    $services = ['sshd'];
    $grepArgs = '';
    foreach ($services as $service) {
        $grepArgs .= " -e $service";
    }
    $grepSrvCmd = "grep $grepArgs";

    // generate cat command to concatenate all log files
    $catCmd = 'cat ' . implode(' ', $logFilePaths);

    // build and execute UNIX command to generate filtered log
    $cmd = "$catCmd | $grepSrvCmd | $grepIPCmd > $tmpFilePath";
    exec($cmd);

    // use UNIX wc command to count lines in temporary file
    $cmd = "wc -l $tmpFilePath";
    $fp = popen($cmd, 'r');
    $lineCount = intval(fgets($fp));
    pclose($fp);

    // calculate number of pages
    $pageCount = ceil($lineCount / $linesPerPage);

    // calculate the page number we'll actually be returning
    $page = min($page, $pageCount);

    // build UNIX command to get the lines we want
    $firstLine = $page * $linesPerPage + 1;
    $lastLine = $firstLine + ($linesPerPage - 1);
    $cmd = "tail -n $lastLine $tmpFilePath | head -n $linesPerPage | tac";

    // read the lines from UNIX pipe
    $fp = popen($cmd, 'r');
    $lines = [];
    while ($line = fgets($fp)) {
        $lines[] = $line;
    }
    pclose($fp);

    // delete temp file
    unlink($tmpFilePath);

    // Read in CLF header name array from clfhead.json
    $headers = json_decode(file_get_contents('auth/loghead.json'));

    // Create array of auth log lines
    $logLines = [];
    $logLines[] = $headers;

    // Process each line and add to the array
    $pageLineCount = 0;
    foreach ($lines as $line) {
        // parse log line
        $data = parseAuthLogLine($line);

        // determine status based on $data[2]
        $status = getAuthLogStatus($data[2]);

        $logLines[] = [$data[0], $data[1], $data[2], $status];
        $pageLineCount++;
        if ($pageLineCount >= $linesPerPage) {
            break;
        }
    }

    // Output $logLines, $page and $lineCount as a JSON dictionary
    echo json_encode([
        'page' => $page,
        'pageCount' => $pageCount,
        'lineCount' => $lineCount,
        'logLines' => $logLines
    ]);
}
