<?php

// provide the current UTF time in CLF format via JSON
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode(get_time());
}

// get server time in ISO 8601 format with ms
function get_time() {
    $now = new DateTime();
    return $now->format('Y-m-d\TH:i:s.v\Z');
}

// get server time in ISO 8601 format
function get_time_iso() {
    return date('c');
}

// get server time in RFC 2822 format
function get_time_rfc() {
    return date('r');
}

// get server time in W3C format
function get_time_w3c() {
    return date('c');
}
