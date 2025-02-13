<?php
if ($value && $value instanceof \DateTime) {
    $current = new \DateTime();
    $currentYear = $current->format("Y");
    $year = $value->format("Y");
    $date = ($currentYear == $year ? $value->format("M-d") : $value->format("Y-M-d"));
    $time = $value->format("H:i");
    print("<ul class='nowrap'><li>$date</li><li>$time</li></ul>");
}
