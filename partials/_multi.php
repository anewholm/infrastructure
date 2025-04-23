<?php
use Illuminate\Database\Eloquent\Collection;
use Acorn\Traits\PathsHelper;
use Backend\Classes\ListColumn;
use \Carbon\CarbonInterval;

if (!isset($record)) throw new \Exception("_multi.php is a column partial only");

if (is_null($value)) {
    // This can happen when we have a *Many collection with a [name] valueFrom 
    // like legalcase[many-somethings][name]
    // because the back-column legalcase[many-somethings] returns a collection, which has no name attribute
    // We try to strip the [name] to see what happens
    // TODO: We need to put more checks in this back-column attempt
    if ($backColumnName = PathsHelper::backColumnName($column->columnName, FALSE)) {
        $column         = new ListColumn($backColumnName, '');
        // If there is a problem this will just return NULL again
        $value          = $column->getValueFromData($record);
        if (!$value instanceof Collection) $value = NULL;
    }
}

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

    // Custom multi directives
    $sum   = FALSE;
    $total = NULL;
    if (isset($column->config['multi'])) {
        $multi = $column->config['multi'];
        if (isset($multi['sum'])) $sum = new ListColumn($multi['sum'], '');
    }

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
    if ($count) {
        $i     = 0;
        print("<ul id='$multiId' class='multi'>");
        $value->each(function ($model) use (&$i, &$limit, &$total, $sum, $valueFrom, $action, $multiId, $useLinkedPopups) {
            $controller = $model->controllerFullyQualifiedClass();
            
            // Name resolution
            $name = '';
            if      (method_exists($model, $valueFrom)) $name = $model->$valueFrom();
            else if ($model->hasAttribute($valueFrom)) $name = $model->$valueFrom;
            if (!$name) {
                $name = '&lt;noname&gt;';
            }
            
            // Output LI item
            print('<li>');
            $nameEscaped = e($name);
            if ($useLinkedPopups) {
                $dataRequestData = array(
                    'route'   => "$controller@$action",
                    'params'  => [$model->id],
                    'dataRequestUpdate' => array('multi' => $multiId),
                );
                $dataRequestDataEscaped = e(substr(json_encode($dataRequestData), 1, -1));
                print("<a
                    data-handler='onPopupRoute'
                    data-request-data='$dataRequestDataEscaped'
                    data-control='popup'>"
                );
            }
            print($nameEscaped);
            if ($useLinkedPopups) print('</a>');
            print('</li>');

            if ($sum) {
                $sumValue = $sum->getValueFromData($model);
                if (is_numeric($sumValue)) $total = ($total ?: 0) + $sumValue;
                else if ($sumValue instanceof CarbonInterval) $total = ($total ?: new CarbonInterval(0))->add($sumValue);
            }

            // False exists the loop
            // TODO: Continue when $sum
            $continue = (++$i < $limit);
            return $continue;
        });
        print('</ul>');

        if ($count > $limit) {
            // Leave this "more" link to simply open the full record update screen
            $more = e(trans('more...'));
            print("<a class='more'>$more</a>");
        } else {
            if ($sum) print("<div class='multi-total'>$total</div>");
        }
    } else {
        print('-');
    }
} 
