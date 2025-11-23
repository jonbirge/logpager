<?php

include 'searchparse.php';

$allowedTypes = ['auth', 'traefik', 'clf'];

$type = $_GET['type'] ?? 'auth';
$search = $_GET['search'] ?? null;
$summary = $_GET['summary'] ?? 'true';
$doSummary = ($summary === 'true');

if (!in_array($type, $allowedTypes, true)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

if ($search !== null && $search !== '') {
    $searchDict = parseSearch($search);
} else {
    $searchDict = array('mode' => 'legacy');
}

$searchInc = $type . '/search.php';

if (!file_exists($searchInc)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

include $searchInc;
search($searchDict, $doSummary);
