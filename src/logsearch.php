<?php

include 'searchparse.php';

// Get parameters from URL
$type = $_GET['type'] ?? "clf";  // auth or clf
$search = $_GET['search'] ?? "404";  // search string

$searchDict = parseSearch($search);

switch ($type) {
    case 'clf':
        include 'clfsearch.php';
        clfSearch($searchDict);
        break;
    case 'auth':
        include 'authsearch.php';
        authSearch($searchDict);
        break;
    default:
        echo "<p>Invalid log type: $type</p>";
}
