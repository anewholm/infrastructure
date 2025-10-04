<?php
$columnName = $column->columnName;

if (isset($column->config['relation'])) {
    // Relations and sub-objects
    $relation        = $column->config['relation'];
    $treeCollection  = $record->$relation()->get();
    $columnNameParts = Winter\Storm\Html\Helper::nameToArray($columnName);
    $columnBaseName  = $columnNameParts[0];
    $subRelation     = (isset($columnNameParts[1]) ? $columnNameParts[1] : NULL);

    // Depth
    $parent              = 1;
    $columnBaseNameParts = explode('_', $columnBaseName);
    if (isset($columnBaseNameParts[1]) && is_numeric($columnBaseNameParts[1])) 
        $parent = (int) $columnBaseNameParts[1];

    // Gather
    $nodes = array();
    foreach ($treeCollection as $node) {
        for ($i = 0; $i < $parent; $i++) if ($node) $node = $node->parent()->first();
        if ($node) {
            if ($subRelation) $node = $node->$subRelation;
            $nodes[$node->id] = $node;
        }
    }

    // Output
    if ($nodes) {
        print("<ul>");
        foreach ($nodes as $node) {
            $name = $node->name;
            print("<li>$name</li>");
        }
        print("</ul>");
    } else {
        print('-');
    }
} else {
    if (env('APP_DEBUG')) print("Relation required for $columnName");
}
?>