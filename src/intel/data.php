<?php

// Get the IP address from the query string
$target_ip = $_GET['ip'] ?? '';

// Call the function to get the data
$ipinfo = getIntelData($target_ip);

// Output the data in JSON format
echo json_encode($ipinfo);

function getIntelData($ip) {
    // geo lookup
    $ipURL = "http://ip-api.com/json/$ip?fields=17563647";
    //$ipURL = "https://nyc.birgefuller.com/logs/geo.php?ip=$ip";
    $ipinfo = file_get_contents($ipURL);
    $ipinfo = json_decode($ipinfo, true);
    // strip some of the dumber fields
    unset($ipinfo['status']);
    unset($ipinfo['timezone']);
    unset($ipinfo['query']);
    unset($ipinfo['lat']);
    unset($ipinfo['lon']);
    // cidr lookup
    $whois = shell_exec("whois $ip");
    // find the CIDR block in the whois output of the form xxx.xxx.xxx.xxx/xx
    preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}/', $whois, $cidr);
    $ipinfo['cidr'] = $cidr[0];

    return $ipinfo;
}
