<?php

// Get parameters from URL
$type = $_GET['type'] ?? 'clf';  // auth or clf
$search = $_GET['search'] ?? null;  // search string

switch ($type) {
    case 'clf':
        include 'clfheatmap.php';
        clfHeatmap($search);
        break;
    case 'auth':
        include 'authheatmap.php';
        authHeatmap($search);
        break;
    default:
        echo "<p>Invalid log type: $type</p>";
}

?>
