<?php
 $stateIndicatorParts = explode(',', substr($value, 1, -1));
 $stateLabel = $stateIndicatorParts[0];
 $stateClass = (isset($stateIndicatorParts[1]) ? $stateIndicatorParts[1] : NULL);

// list.css will process this to elevate it to row level
if ($stateLabel) {
    if (strstr($stateLabel, '::') === FALSE) {
        $stateLabel = $record->translationDomainModel($stateLabel);
    }

    $stateLabelTrans = e(trans($stateLabel));
    print(<<<HTML
        <div class="state-indicator $stateClass">$stateLabelTrans</div>
    HTML
    );
}
?>
