<?php

include 'searchparse.php';

// Get parameters from URL
$type = $_GET['type'] ?? 'clf';  // auth or clf
$search = $_GET['search'] ?? null;  // search string

$searchDict = parseSearch($search);

// Include the appropriate heatmap function based on the log type
$searchInc = $type . '/heatmap.php';

// Check to see if the file exists
if (!file_exists($searchInc)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

// Execute the appropriate heatmap function
include $searchInc;
heatmap($searchDict);
