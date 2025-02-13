<?php
use Illuminate\Database\Eloquent\Collection;

if ($value) {
    $multiId   = "$record->id-$column->columnName";
    $isNested  = (strstr($column->columnName, '[') !== FALSE);
    // Custom config settings
    $limit     = (isset($column->config['limit'])  ? $column->config['limit']  : 4);
    $action    = (isset($column->config['action']) ? $column->config['action'] : 'update');
    $useLinkedPopups = (isset($column->config['use-linked-popups']) ? $column->config['use-linked-popups'] : FALSE);
    // We do not respect the values passed in value
    // instead, we create a collection and re-apply the valueFrom/select logic
    // this is to standardise the Collection display
    // When nested, the valueFrom is forced to the columnName
    // so we introduce a non-standard option nestedValueFrom
    // Value selection failover: nestedValueFrom, valueFrom, select, 'name' 
    // WinterCMS default behaviour with multi-level relations is to apply the valueFrom/select to the top level relation only
    // we apply it to the final Collection result
    $hasNestedDirective = ($isNested && isset($column->config['nestedValueFrom']));
    $valueFrom = (
        $hasNestedDirective ? $column->config['nestedValueFrom'] :
        ($column->valueFrom ? $column->valueFrom :
        ($column->sqlSelect ? $column->sqlSelect :
        'name'
    )));
    $relation = $column->relation;

    // The field name is the name of the Model relation
    // so the relation is automatically used
    // Some considered scenarios:
    //   1) relation & valueFrom => array(id => valueFrom, ...)
    //   2) valueFrom only => string
    //   3) relation only  => Collection
    //   4) no relation or valueFrom => Collection
    // relation: directive is necessary for searcheable and sortable
    // valueFrom: is honoured on the Collections below
    if (is_string($value)) {
        $col   = $column->columnName;
        // The relation associated with the column is invoked ($record->$col()).
        // The related records are fetched as a Collection using the get() method.
        // and then the valueFrom is applied, essentially re-creating the same array or string indirectly
        $model = &$record;
        if ($relation && $model->hasRelation($relation)) $model = $model->$relation;
        $value = $model->$col;
    } else if (is_array($value)) {
        // The $record is already the relation: model
        $col   = $column->columnName;
        $value = $record->$col;
    }

    // Check that the value is now a collection
    if (!$value instanceof Collection) {
        $valueType = (is_object($value) ? get_class($value) : gettype($value));
        throw new \Exception("$column->columnName has type [$valueType] which cannot be rendered by _multi");
    }

    $count = $value->count();
    $i     = 0;
    print("<ul id='$multiId' class='multi'>");
    $value->each(function ($model) use (&$i, &$limit, $valueFrom, $action, $multiId, $useLinkedPopups) {
        // A method is used because of Encapsulation
        $name       = $model->$valueFrom(); // TODO: AA\User and FQN?
        $id         = $model->id();
        $controller = $model->controllerFullyQualifiedClass();
        $dataRequestData = array(
            'route'   => "$controller@$action",
            'params'  => [$id],
            'dataRequestUpdate' => array('multi' => $multiId),
        );
        $nameEscaped = e($name);

        // Output LI item
        print('<li>');
        if ($useLinkedPopups) {
            $dataRequestDataEscaped = e(substr(json_encode($dataRequestData), 1, -1));
            print(<<<HTML
            <a
                data-handler="onPopupRoute"
                data-request-data="$dataRequestDataEscaped"
                data-control="popup"
            >$nameEscaped</a>
HTML
            );
        } else {
            print($nameEscaped);
        }
        print('</li>');

        // False exists the loop
        $continue = (++$i < $limit);
        return $continue;
    });
    print('</ul>');

    if ($count > $limit) {
        $more = e(trans('more...'));
        print("<a class='more'>$more</a>");
    }
}
