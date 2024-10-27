<?php
$modelArrayName  = $formModel->unqualifiedClassName();
$actionFunctions = ($formModel->actionFunctions ?: array());
foreach ($actionFunctions as $fnName => &$parameters) {
    $parameters['id'] = $formModel->id();
}

// 1to1 BelongsTo relations
foreach ($formModel->belongsTo as $name => $definition) {
    $formModel->load($name);
    if ($relatedModel = $formModel->getRelation($name)) {
        if ($relatedModel->actionFunctions) {
            // Existing actions take precedence
            // TODO: Write the sub-model id
            foreach ($relatedModel->actionFunctions as $fnName => &$parameters) {
                $parameters['id'] = $relatedModel->id();
                $actionFunctions[$fnName] = $parameters;
            }
        }
    }
}

if (count($actionFunctions)) {
    print('<ul class="action-functions">');
    foreach ($actionFunctions as $fnName => $parameters) {
        $fnNameParts = explode('_', $fnName);
        $nameParts   = array_slice($fnNameParts, 5);
        $title       = e(trans(Str::title(implode(' ', $nameParts))));
        $dataRequestData = e(substr(json_encode(array(
            'name'       => $fnName,
            'parameters' => $parameters,
            'arrayname'  => $modelArrayName,
            'id'         => $parameters['id'],
        )), 1,-1));

        print(<<<HTML
            <li><a href="#"
                data-control="popup"
                data-request-data='$dataRequestData'
                data-handler="onActionFunction"
            >$title</a></li>
    HTML
        );
    }
    print('</ul>');
}
?>
