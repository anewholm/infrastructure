<?php
$value = preg_replace('/<script[^>]*[^>]*/i', '', $value);
$value = preg_replace('/<style[^>]*[^>]*/i', '', $value);
print("<div class='html-partial'>$value</div>");