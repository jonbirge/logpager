<?php

$defaultLines = 12;
$allowedTypes = ['auth', 'clf', 'traefik'];

$type = $_GET['type'] ?? 'auth';
$page = intval($_GET['page'] ?? 0);
$linesPerPage = intval($_GET['n'] ?? $defaultLines);

$page = max(0, $page);
$linesPerPage = $linesPerPage > 0 ? $linesPerPage : $defaultLines;

if (!in_array($type, $allowedTypes, true)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

$searchInc = $type . '/tail.php';

if (!file_exists($searchInc)) {
    echo "<p>Invalid log type specified.</p>";
    return;
}

include $searchInc;
tail($page, $linesPerPage);
