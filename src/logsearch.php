<?php

// Include the searchparse.php file, needed by this and all ___search.php files
include 'searchparse.php';

// Get parameters from URL
$type = $_GET['type'] ?? "auth";  // auth or clf
$search = $_GET['search'] ?? "publickey";  // search string
$summary = $_GET['summary'] ?? "true";  // true or false

$doSummary = $summary === "true";
$searchDict = parseSearch($search);

switch ($type) {
    case 'clf':
        include 'clfsearch.php';
        clfSearch($searchDict, $doSummary);
        break;
    case 'auth':
        include 'authsearch.php';
        authSearch($searchDict, $doSummary);
        break;
    default:
        echo "<p>Invalid log type: $type</p>";
}
