<?php
if (is_null($value)) {
    print("<span class='counter count-null'>-</span>");
}
else {
    $config = &$listColumn->config;

    if (isset($config['prefix']) && $value) {
        $prefix = e(trans($config['prefix']));
        print("<span class='prefix'>$prefix</span> ");
    }

    print("<span class='counter count-$value'>$value</span>");

    if (isset($config['suffix']) && $value) {
        $suffix = e(trans($config['suffix']));
        print(" <span class='suffix'>$suffix</span>");
    }
}