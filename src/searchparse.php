<?php

function parseSearch($search) {
    // Check $search string for terms preceded by ip: or date:, and assume there is,
    // at most, one of each. Remove the terms from $string and set $ip and $date to
    // the values. If $string is empty afterwards, set it to null.
    $ip = null;
    $dateStr = null;
    if ($search) {
        $search = trim($search);
        $ipPos = strpos($search, 'ip:');
        $datePos = strpos($search, 'date:');
        if ($ipPos !== false) {
            $ip = substr($search, $ipPos + 3);
            $search = trim(substr($search, 0, $ipPos));
        }
        if ($datePos !== false) {
            $dateStr = substr($search, $datePos + 5);
            $search = trim(substr($search, 0, $datePos));
        }
        if ($search === '') {
            $search = null;
        }
    }

    return [$search, $ip, $dateStr];
}

?>
