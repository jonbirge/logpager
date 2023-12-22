<?php

// Get parameters from URL
$ipAddress = $_GET['ip'];

// Return the host name for the IP address
echo htmlspecialchars(gethostbyaddr($ipAddress));

?>
