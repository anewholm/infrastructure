<?php
$current = FALSE;
if ($value instanceof Model) {
    $current = $value->current;
} else {
    $current = $listRecord->current;
}

if ($value instanceof Model) {
    $value = $value->name;
}

$class = ($current ? 'current' : 'not-current');
print("<span class='$class'>$value</span>");