<?php

// provide the current UTF time in CLF format via JSON
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode(get_time());
}

// get server time in CLF format in UTF (minus the time zone offset)
function get_time() {
    return date('d/M/Y:H:i:s');
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
