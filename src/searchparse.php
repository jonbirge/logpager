<?php

function parseSearch($search)
{
    // Check $search string for terms preceded by ip: or date: (etc.) and assume there is,
    // at most, one of each (for now). If $string is empty afterwards, set it to null.
    $ip = null;
    $dateStr = null;
    $stat = null;
    if ($search) {
        $search = trim($search);
        $ipPos = strpos($search, 'ip:');
        $datePos = strpos($search, 'date:');
        $statPos = strpos($search, 'stat:');
        if ($ipPos !== false) {
            $ip = substr($search, $ipPos + 3);
            $search = trim(substr($search, 0, $ipPos));
        }
        if ($datePos !== false) {
            $dateStr = substr($search, $datePos + 5);
            $search = trim(substr($search, 0, $datePos));
        }
        if ($statPos !== false) {
            $stat = substr($search, $statPos + 5);
            $search = trim(substr($search, 0, $statPos));
        }
        if ($search === '') {
            $search = null;
        }

        // return as dictionary array
        return array(
            'search' => $search,
            'ip' => $ip,
            'date' => $dateStr,
            'stat' => $stat
        );
    } else {
        return null;
    }
}
