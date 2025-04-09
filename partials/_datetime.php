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
    $week      = $value->format("W");
    $month     = $value->format("m");
    $monthName = $value->format("M");
    $timeZone  = $value->format("eP");
    $timeTense = DateTimeHelper::timeTense($value);

    // Translations
    $dayLabel        = e(trans('acornassociated::lang.models.general.dateformats.day'));
    $weekInYearLabel = e(trans('acornassociated::lang.models.general.dateformats.weekinyear'));
    $monthLabel      = e(trans('acornassociated::lang.models.general.dateformats.month'));
    $yearLabel       = e(trans('acornassociated::lang.models.general.dateformats.year'));
    $timezoneLabel   = e(trans('acornassociated::lang.models.general.dateformats.timezone'));

    // Title
    $title     = implode('<br/>', array(
        "<span class='timetense'>$timeTense</span>", 
        "<b>$dayLabel</b>: $dayName ($day)", 
        "<b>$weekInYearLabel</b>: $week", 
        "<b>$monthLabel</b>: $monthName ($month)", 
        "<b>$yearLabel</b>: $year",
        "<b>$timezoneLabel</b>: $timeZone"
    ));

    $date = ($isCurrentYear ? $value->format("M-d") : $value->format("Y-M-d"));
    $time = $value->format("H:i");
    
    print(<<<HTML
        <div>
            <div class="tooltip fade top">
                <div class="tooltip-arrow"></div>
                <div class="tooltip-inner">$title</div>
            </div>
            <ul class="nowrap">
                <li><span class="hover-indicator">$date</span></li>
                <li><span class="hover-indicator">$time</span></li>
            </ul>
        </div>
HTML
    );
} else {
    print('-');
}
