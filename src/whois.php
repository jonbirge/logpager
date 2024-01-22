<?php

// Cache control
header("Cache-Control: max-age=86400, must-revalidate");

// Get parameters from URL
$ipAddress = $_GET['ip'];

// Send ip address to linux whois command
$whois = shell_exec("whois $ipAddress");

// Return answer
echo $whois;

?>
