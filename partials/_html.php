<?php
$value = preg_replace('/<script[^>]*[^>]*/i', '', $value);
$value = preg_replace('/<style[^>]*[^>]*/i', '', $value);
if (isset($listColumn)) {
    if (isset($listColumn->config['limit'])) $value = Str::limit($value, $listColumn->config['limit']);
}
print("<div class='html-partial'>$value</div>");