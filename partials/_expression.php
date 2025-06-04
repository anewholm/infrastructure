<?php
$value = preg_replace('/<([^>]+)>/', '<span class="replacement">&lt;$1&gt;</span>', $value);
$value = preg_replace('/([a-z]+)\(([^)]*)\)/', '<span class="function">$1</span>($2)', $value);
$value = preg_replace('/:([^:]+):/', '<span class="token">:$1:</span>', $value);
$value = preg_replace('/\/\.\*/', '/<span class="any">.*</span>', $value);
$value = preg_replace('/([,) ])(-?[0-9.]+)/', '$1<span class="number">$2</span>', $value);
$value = preg_replace('/(-?[0-9.]+)([,) ])/', '<span class="number">$1</span>$2', $value);

print("<div class='expression'>$value</div>");