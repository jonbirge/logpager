<?php

$exclusionFileName = "/excludes.json";

function getExcludedIPs() {
    global $exclusionFileName;
    // Read the exclusions file and convert it to a PHP array
    $exclusions = json_decode(file_get_contents($exclusionFileName), true);
    
    // If the exclusions file is empty, return an empty array
    if ($exclusions == null) {
        return array();
    } else {
        return $exclusions;
    }
}

function addExcludedIP($ip) {
    global $exclusionFileName;
    // Read the exclusions file and convert it to a PHP array
    $exclusions = json_decode(file_get_contents($exclusionFileName), true);
    
    // If the exclusions file is empty, create an empty array
    if ($exclusions == null) {
        $exclusions = array();
    }
    
    // Add the IP to the exclusions array
    $exclusions[] = $ip;
    
    // Write the exclusions array to the exclusions file
    file_put_contents($exclusionFileName, json_encode($exclusions));
}

function removeExcludedIP($ip) {
    global $exclusionFileName;
    // Read the exclusions file and convert it to a PHP array
    $exclusions = json_decode(file_get_contents($exclusionFileName), true);
    
    // If the exclusions file is empty, return an empty array
    if ($exclusions == null) {
        return;
    }
    
    // Find the IP in the exclusions array
    $index = array_search($ip, $exclusions);
    
    // If the IP is found, remove it from the exclusions array
    if ($index !== false) {
        unset($exclusions[$index]);
    }
    
    // Write the exclusions array to the exclusions file
    file_put_contents($exclusionFileName, json_encode($exclusions));
}

?>
