<?php
use System\Helpers\DateTime as DateTimeHelper;

if ($value) {
    if (! $value instanceof \DateTime) $value = new \DateTime($value);

    $current       = new \DateTime();
    $currentYear   = $current->format("Y");
    $year          = $value->format("Y");
    $isCurrentYear = ($currentYear == $year);

    // Hover
    $day       = $value->format("d");
    $dayName   = $value->format("D");
    $week      = $value->format("w");
    $month     = $value->format("m");
    $monthName = $value->format("M");
    $timeTense = DateTimeHelper::timeTense($value);

    $title     = implode(', ', array(
        $timeTense, 
        "Day: $dayName ($day)", 
        "Week: $week", 
        "Month: $monthName ($month)", 
        "Year: $year"
    ));

    $date = ($isCurrentYear ? $value->format("M-d") : $value->format("Y-M-d"));
    $time = $value->format("H:i");
    
    print(<<<HTML
        <ul title="$title" class="nowrap" data-toggle="tooltip">
            <li><span class="hover-indicator">$date</span></li>
            <li><span class="hover-indicator">$time</span></li>
        </ul>
HTML
    );
}
