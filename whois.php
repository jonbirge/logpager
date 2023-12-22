<?php

// Get parameters from URL
$ipAddress = $_GET['ip'];

// Send ip address to linux whois command
$whois = shell_exec("whois $ipAddress");

// Return answer
echo $whois;

?>
