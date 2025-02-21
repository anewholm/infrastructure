<?php
use System\Helpers\DateTime as DateTimeHelper;

if ($value) {
    if (! $value instanceof \DateTime) $value = new \DateTime($value);
    $value = Backend::makeCarbon($value); // To set the user / cms timezone preference also

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
    $timeZone  = $value->format("e P");
    $timeTense = DateTimeHelper::timeTense($value);

    $title     = implode(', ', array(
        $timeTense, 
        "Day: $dayName ($day)", 
        "Week: $week", 
        "Month: $monthName ($month)", 
        "Year: $year",
        "Timezone: $timeZone"
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
