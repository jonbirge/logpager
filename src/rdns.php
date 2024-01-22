<?php

// Cache control
header("Cache-Control: max-age=86400, must-revalidate");

// Get parameters from URL
$ipAddress = $_GET['ip'];

// Return the host name for the IP address
echo htmlspecialchars(gethostbyaddr($ipAddress));

?>
