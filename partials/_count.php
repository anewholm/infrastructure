<?php
if (is_null($value)) print("<span class='counter count-null'>-</span>");
else print("<span class='counter count-$value'>$value</span>");