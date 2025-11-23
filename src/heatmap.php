<?php

include 'searchparse.php';

$allowedTypes = ['auth', 'clf', 'traefik'];

$type = $_GET['type'] ?? 'clf';
$search = $_GET['search'] ?? null;

if (!in_array($type, $allowedTypes, true)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

$searchDict = parseSearch($search);

$searchInc = $type . '/heatmap.php';

if (!file_exists($searchInc)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

include $searchInc;
heatmap($searchDict);
