<?php

// Include the searchparse.php file, needed by this and all search.php files
include 'searchparse.php';

// Get parameters from URL
$type = $_GET['type'] ?? "auth";  // auth, traefik, or clf
$search = $_GET['search'] ?? "publickey";  // search string
$summary = $_GET['summary'] ?? "true";  // true or false
$doSummary = $summary === "true";
$searchDict = parseSearch($search);

// Include the appropriate heatmap function based on the log type
$searchInc = $type . '/search.php';

// Check to see if the file exists
if (!file_exists($searchInc)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

// Execute the appropriate function
include $searchInc;
search($searchDict, $doSummary);

