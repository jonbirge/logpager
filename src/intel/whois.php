<?php

// Cache control
header("Cache-Control: max-age=86400, must-revalidate");

// Get parameters from URL
$ipAddress = $_GET['ip'];

// Send ip address to linux whois command
$whois = shell_exec("whois $ipAddress");

// Remove all lines in $whois that start with either % or #
$whois = preg_replace('/^%.*\n/m', '', $whois);
$whois = preg_replace('/^#.*\n/m', '', $whois);

// Remove all blank lines in $whois
$whois = preg_replace('/^\s*[\r\n]/m', '', $whois);

// Look for CIDR in $whois and turn it into a link
// $whois = preg_replace('/([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}/',
//    '<a href="https://www.google.com/search?q=$0" target="_blank">$0</a>', $whois);

// Return formatted answer
echo "<pre>";
echo $whois;
echo "</pre>";

