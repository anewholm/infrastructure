<?php
$columnName = $column->columnName;

if (isset($column->config['relation'])) {
    // Relations and sub-objects
    $relation        = $column->config['relation'];
    // We move to the parent hierarchy _with_ global scopes
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
        for ($i = 0; $i < $parent; $i++) {
            if ($node) {
                if ($node->hasAttribute('parent_id')) {
                    // Global Scopes prevent the retrieval of the parent_id attribute
                    // so we make a manual request
                    if ($parent_id = $node->attributes['parent_id']) {
                        $node = $node::where('id', '=', $parent_id)->withoutGlobalScopes()->first();
                    } else $node = NULL;
                } else {
                    $node = $node->parent()->withoutGlobalScopes()->first();
                }
            }
        }
        if ($node) {
            if ($subRelation) $node = $node->$subRelation()->withoutGlobalScopes()->first();
            $nodes[$node->id] = $node;
        }
    }

    // Output
    if ($nodes) {
        print("<ul>");
        foreach ($nodes as $node) {
            print('<li>');
            print($node->name);
            if ($leafTable = $node->leaf_table) print(" <span class='leaf-type'>$leafTable</span>");
            print('</li>');
        }
        print("</ul>");
    } else {
        print('-');
    }
} else {
    if (env('APP_DEBUG')) print("Relation required for $columnName");
}
?>