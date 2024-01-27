<?php

// Get parameters from URL
$type = $_GET['type'] ?? "clf";  // auth or clf
$search = $_GET['search'] ?? null;  // search string
$page = $_GET['page'] ?? 0;  // page size
$linesPerPage = $_GET['n'] ?? 16;  // number of lines per page

switch ($type) {
    case 'clf':
        include 'clftail.php';
        clfTail($search, $page, $linesPerPage);
        break;
    case 'auth':
        include 'authtail.php';
        authTail($search, $page, $linesPerPage);
        break;
    default:
        echo "<p>Invalid log type: $type</p>";
}
