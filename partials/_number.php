<?php
// Better NULL handling
if (is_null($value)) {
    print('-');
} else {
    $config = &$listColumn->config;

    if (isset($config['bar']) && $value) { // Do not show for zero scores
        print("<div class='bar'><div class='inner' style='width:$value%'></div></div>");
    }    
    
    print('<div class="number">');
    if (isset($config['prefix'])) {
        $prefix = e(trans($config['prefix']));
        print("<span class='prefix'>$prefix</span> ");
    }

    if ($listColumn->format) {
        print("<span title='$value'>");
        print(\sprintf($listColumn->format, $value));
        print("</span>");
    } else {
        print($value);
    }

    if (isset($config['suffix'])) {
        $suffix = e(trans($config['suffix']));
        print(" <span class='suffix'>$suffix</span>");
    }
    print('</div>');
}