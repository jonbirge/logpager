<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Get the target IP from the URL ip parameter. If it's not there, use client IP address
$target_ip = $_GET['ip'] ?? ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR']);
$target_ip = htmlspecialchars($target_ip);

function getIntelData($ip) {
    $ipURL = "http://ip-api.com/json/$ip?fields=17563647";
    $ipinfo = file_get_contents($ipURL);
    $ipinfo = json_decode($ipinfo, true);

    // Strip some of the more useless fields
    unset($ipinfo['status'], $ipinfo['timezone'], $ipinfo['query'], $ipinfo['lat'], $ipinfo['lon'], $ipinfo['countryCode']);

    // Remove any blank fields
    $ipinfo = array_filter($ipinfo);

    // Whois lookup
    $whois = shell_exec("whois $ip");

    // Extract CIDR or IP range from the whois output
    if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}/', $whois, $cidr)) {
        $ipinfo['cidr'] = $cidr[0];
    } elseif (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s*-\s*(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $whois, $range)) {
        $start_ip = $range[1];
        $end_ip = $range[2];
        $ipinfo['cidr'] = ipRange2cidr($start_ip, $end_ip) ?? "$start_ip→$end_ip";
    }

    return $ipinfo;
}

function ipRange2cidr($start_ip, $end_ip) {
    $start = ip2long($start_ip);
    $end = ip2long($end_ip);
    $mask = $start ^ $end;
    $masklen = 32 - log(($mask + 1), 2);

    if (fmod($masklen, 1) < 0.0001) {
        return long2ip($start) . "/" . round($masklen);
    }
    return null;
}

$intel_data = getIntelData($target_ip);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet" type="text/css">
    <link rel="shortcut icon" href="intel/favicon.ico" type="image/x-icon" />
    <title>Target intel: <?php echo $target_ip; ?></title>
</head>

<body>
    <div class="container">
        <h1>Target intel: <?php echo $target_ip; ?></h1>
        <div id="intel">
            <table>
                <tr><th>Property</th><th>Value</th></tr>
                <?php
                    foreach ($intel_data as $key => $value) {
                        if ($value === true) {
                            $value = '<span style="color: green;">●</span>';
                        } elseif ($value === false) {
                            $value = '<span style="color: darkred;">●</span>';
                        }
                        echo "<tr><td>{$key}</td><td>{$value}</td></tr>";
                    }
                ?>
            </table>
        </div>
        <button class="toggle-button" onclick="runAll()">Execute all...</button>

        <h2>nmap scan</h2>
        <div id="scan-buttons">
            <button class="toggle-button green" onclick="runScan('quick')">Quick port scan</button>
            <button class="toggle-button green" onclick="runScan('deep')">Deep port scan</button>
        </div>
        <div id="scan" class="scan">
            <!-- This is where the scan will go -->
        </div>

        <h2>route trace</h2>
        <div id="trace-button">
            <button class="toggle-button green" onclick="runTrace()">Execute traceroute</button>
        </div>
        <div id="trace" class="trace">
            <!-- This is where the traceroute will go -->
        </div>

        <h2>ping stats</h2>
        <div id="ping-button">
            <button class="toggle-button green" onclick="runPing()">Ping target</button>
        </div>
        <div id="ping-chart">
            <!-- This is where the chart will go -->
        </div>

        <h2>whois</h2>
        <div id="whois-button">
            <button class="toggle-button green" onclick="runWhois()">Execute whois</button>
        </div>
        <div id="whois" class="whois">
            <!-- This is where the whois will go -->
        </div>

        <!-- version footer -->
        <div>1.9.1b</div>
    </div>

    <script src="timeutils.js"></script>
    <script src="blacklist.js"></script>
    <script src="intel/intel.js?ver=1"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>

</html>
