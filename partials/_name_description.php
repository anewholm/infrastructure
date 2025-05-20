<?php
if ($value) {
    $name = $value->name;
    if (preg_match_all('/&lt;([^&]+)&gt;/', $name, $matches)) {
        foreach ($matches[1] as $match) {
            $subValue = $listRecord->$match;
            if ($subValue instanceof Model) {
                $name = str_replace(
                    "&lt;$match&gt;", 
                    "<span class='variable'>$subValue->name</span>", 
                    $name
                );
            }
        }
    }
    
    $description = $value->description;
    if (preg_match_all('/&lt;([^&]+)&gt;/', $description, $matches)) {
        foreach ($matches[1] as $match) {
            $subValue = $listRecord->$match;
            if (is_null($subValue)) {
                $subValue = "$match?";
            } else if ($subValue instanceof Model) {
                $subValue = $subValue->name;
            }
            $description = str_replace(
                "&lt;$match&gt;", 
                "<span class='variable'>$subValue</span>", 
                $description
            );
        }
    }

    $nameEscaped = e($name);
    print(<<<HTML
        <div class="name-description">
            <div class="name">$nameEscaped</div>
            <div class="description">$description</div>
        </div>
HTML
    );
}