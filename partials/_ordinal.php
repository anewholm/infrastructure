<?php
use Acorn\Model;

if (is_numeric($value)) {
    $value = (int) $value;
    
    $ordinal = Model::ordinal($value);
    $value   = "<span class='ordinal-value'>$value</span><span class='ordinal'>$ordinal</span>";
}

$config = &$listColumn->config;
if (isset($config['prefix'])) {
    $prefix = e(trans($config['prefix']));
    print("<span class='prefix'>$prefix</span> ");
}
print($value);
if (isset($config['suffix'])) {
    $suffix = e(trans($config['suffix']));
    print(" <span class='suffix'>$suffix</span>");
}
