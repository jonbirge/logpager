<?php

include 'searchparse.php';

// Get parameters from URL
$type = $_GET['type'] ?? 'clf';  // auth or clf
$search = $_GET['search'] ?? null;  // search string

$searchDict = parseSearch($search);

switch ($type) {
    case 'clf':
        include 'clfheatmap.php';
        clfHeatmap($searchDict);
        break;
    case 'auth':
        include 'authheatmap.php';
        authHeatmap($searchDict);
        break;
    default:
        echo "<p>Invalid log type: $type</p>";
}
