<?php

// Get the IP address from the query string
$target_ip = $_GET['ip'] ?? '';

// Call the function to get the data
$ipinfo = getIntelData($target_ip);

// Output the data in JSON format
echo json_encode($ipinfo);

function getIntelData($ip) {
    // perform geo lookup
    $ipURL = "http://ip-api.com/json/$ip?fields=17563647";
    $ipinfo = file_get_contents($ipURL);
    $ipinfo = json_decode($ipinfo, true);
    
    // strip some of the more useless fields
    unset($ipinfo['status']);
    unset($ipinfo['timezone']);
    unset($ipinfo['query']);
    unset($ipinfo['lat']);
    unset($ipinfo['lon']);

    // remove any blank fields
    $ipinfo = array_filter($ipinfo);
    
    // whois lookup
    $whois = shell_exec("whois $ip");
    
    // first, try to find the CIDR block in the whois output
    preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}/', $whois, $cidr);
    
    if (!empty($cidr)) {
        $ipinfo['cidr'] = $cidr[0];
    } else {
        // if CIDR not found, look for IP range
        preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s*-\s*(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $whois, $range);
        
        if (!empty($range)) {
            $start_ip = $range[1];
            $end_ip = $range[2];
            $cidr = ipRange2cidr($start_ip, $end_ip);
            if ($cidr) {
                $ipinfo['cidr'] = $cidr;
            } else {
                $ipinfo['ip range'] = "$start_ip\u{2192}$end_ip";
            }
        }
    }

    return $ipinfo;
}

// Function to convert IP range to CIDR
function ipRange2cidr($start_ip, $end_ip) {
    $start = ip2long($start_ip);
    $end = ip2long($end_ip);
    $mask = $start ^ $end;
    $masklen = 32 - log(($mask + 1), 2);
    
    // Check if $masklen has a very small fractional part
    if (fmod($masklen, 1) < 0.0001) {
        // Round $masklen to the nearest whole number to avoid small truncation errors
        $masklen = round($masklen);
        return long2ip($start) . "/" . $masklen;
    } else {
        // If we can't create a clean CIDR, return null
        return null;
    }
}
