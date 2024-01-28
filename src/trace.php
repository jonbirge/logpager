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
    <link rel="stylesheet" type="text/css" href="styles.css?version=19">
    <?php
    $target_ip = $_GET['ip'];
    echo "<title>Target intel: $target_ip</title>"
    ?>
</head>

<body>
    <div class="container">
        <?php
        echo "<h1>Target intel: $target_ip</h1>"
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
        <button class="toggle-button" onclick="runAll()">Run all traces...</button>

        <!--
            -->
        <h2>High-rate ping</h2>
        <div id="ping-button">
            <button class="toggle-button green" onclick="runPing()">Run ping</button>
        </div>
        <div id="ping-chart" class="responsive-div">
            <!-- This is where the chart will go -->
        </div>

        <!--
            -->
        <h2>Route to target</h2>
        <div id="trace-button">
            <button class="toggle-button green" onclick="runTrace()">Run traceroute</button>
        </div>
        <div id="trace" class="trace">
            <!-- This is where the traceroute will go -->
        </div>
    </div>

    <script src="trace.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>

</html>
