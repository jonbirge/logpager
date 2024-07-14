<?php

// Defaults
$defaultLines = 12;

// Get parameters from URL
$type = $_GET['type'] ?? "auth";  // auth, clf, or traefik
$page = $_GET['page'] ?? 0;  // page size
$linesPerPage = $_GET['n'] ?? $defaultLines;  // number of lines per page

// Include the appropriate heatmap function based on the log type
$searchInc = $type . '/tail.php';

// Check to see if the file exists
if (!file_exists($searchInc)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

// Execute the appropriate heatmap function
include $searchInc;
tail($page, $linesPerPage);
