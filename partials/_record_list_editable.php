<?php
use Illuminate\Database\Eloquent\Collection;
use Acorn\Traits\PathsHelper;
use Backend\Classes\ListColumn;
use Acorn\Model;
use \Carbon\CarbonInterval;
use Str;

if (!isset($record)) 
    throw new Exception("_record_list_editable.php is a column partial only");
if (!is_array($value)) 
    throw new Exception("_record_list_editable.php requires an array of values column, usually json_agg() + jsonable");

// Take the columns in the record to make multiple list_editables from an array
// This column: scores
//   can include NULLS indicating that the row does not exist yet
// IDs column: score_ids
//   can include NULLS indicating that the row does not exist yet
//   ID used for updating columns in that row
// Create IDs column: exam_material_ids, student_ids, etc.
//   used for creating new rows
$nameSingular = Str::singular($column->columnName);
$mainColumn   = new ListColumn($nameSingular, '');
$mainColumn->displayAs('number', $column->config); // Inherit settings
// Model(View)::$baseTable acorn_exam_scores
//   the source and destination table for the data
// TODO: We could remove the data_entry from the view to get the Model base table
// TODO: model-class: should also be a comment YAML setting on the View
$modelClass   = Str::studly($nameSingular);
if (!class_exists($modelClass)) {
    $recordClassParts = explode('\\', get_class($record));
    array_pop($recordClassParts);
    array_push($recordClassParts, $modelClass);
    $modelClass = implode('\\', $recordClassParts);
}
$jsonFields = $record->getJsonable();

foreach ($value as $i => $subValue) {
    // Null id indicates that the model is new
    // Existing models use string UUIDs
    $id        = $record->ids[$i];
    $model     = ($id ? $modelClass::find($id) : new $modelClass());
    // We assign a global integer for the form display
    if (is_null($id)) $model->id = Model::nextNewModelId();
    $title     = ($record->hasAttribute('titles') ? $record->titles[$i] : NULL);
    
    // Other array like values that should be included for creates
    $attributes = array();
    foreach ($jsonFields as $jsonName) {
        if (   $jsonName != 'ids' 
            && $jsonName != 'titles'
            && $jsonName != $column->columnName 
        ) {
            $attrName  = Str::singular($jsonName);
            $attrValue = $record->$jsonName[$i];
            $attributes[$attrName] = $attrValue;
        }
    }
    
    print($this->makePartial('list_editable', array(
        'record'     => $model, // With id=i if new
        'column'     => $mainColumn,
        'title'      => $title,
        'value'      => $subValue,
        'attributes' => $attributes,
    )));
}