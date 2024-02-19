<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Roboto+Mono&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="styles.css">
    <?php
    // get the target IP from the URL ip parameter. if it's not there use client ip
    if (!isset($_GET['ip'])) {
        $target_ip = $_SERVER['HTTP_X_REAL_IP'] ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'];
    } else {
        $target_ip = $_GET['ip'];
    }
    echo "<title>Target intel: $target_ip</title>"
    ?>
</head>

<body data-ip="<?php echo $target_ip; ?>">
    <div class="container">
        <?php
        echo "<h1>Target intel: $target_ip</h1>";
        // echo each element of $_SERVER
        // echo "<h2>Server information</h2>";
        // echo "<table>";
        // foreach ($_SERVER as $key => $value) {
        //     echo "<tr><td><b>$key</b></td><td>$value</td></tr>";
        // }
        // echo "</table>";
        ?>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <!-- query the ipinfo.io API for the target IP's information and put all data into a table -->
            <?php
            $ipURL = "http://ip-api.com/json/$target_ip?fields=57409535";
            $ipinfo = file_get_contents($ipURL);
            $ipinfo = json_decode($ipinfo, true);
            foreach ($ipinfo as $key => $value) {
                echo "<tr><td><b>$key</b></td><td>$value</td></tr>";
            }
            ?>
        </table>
        <button class="toggle-button" onclick="runAll()">Execute all...</button>

        <h2>Port scan</h2>
        <div id="scan-buttons">
            <button class="toggle-button green" onclick="runScan('quick')">Quick port scan</button>
            <button class="toggle-button green" onclick="runScan('deep')">Deep port scan</button>
        </div>
        <div id="scan" class="scan">
            <!-- This is where the scan will go -->
        </div>

        <h2>Whois</h2>
        <div id="whois-button">
            <button class="toggle-button green" onclick="runWhois()">Execute whois</button>
        </div>
        <div id="whois" class="whois">
            <!-- This is where the whois will go -->
        </div>
        
        <h2>TCP route trace</h2>
        <div id="trace-button">
            <button class="toggle-button green" onclick="runTrace()">Execute traceroute</button>
        </div>
        <div id="trace" class="trace">
            <!-- This is where the traceroute will go -->
        </div>
       
        <h2>Ping timing</h2>
        <div id="ping-button">
            <button class="toggle-button green" onclick="runPing()">Ping target</button>
        </div>
        <div id="ping-chart">
            <!-- This is where the chart will go -->
        </div>

        <!-- version and copyright -->
        <div style="margin-top: 50px;">1.7b3</div>
    </div>

    <script src="intel.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>

</html>
