<?php
$classFQN     = get_class($record);
$classParts   = explode('\\', $classFQN);
$class        = end($classParts);
$column       = $column->getName();
$maxlength    = (isset($column->config['max'])           ? $column->config['max']           : 255 );
$type         = (isset($column->config['type-editable']) ? $column->config['type-editable'] : 'number' );
$required     = (isset($column->config['required'])      ? $column->config['required']      : FALSE );
$placeholder  = (isset($column->config['placeholder'])   ? $column->config['placeholder']   : '' );
$pattern      = (isset($column->config['pattern'])       ? $column->config['pattern']       : '' );
$id           = $record->id;
$valueEscaped = htmlentities($value);

switch ($type) {
    case 'number':
        if (!isset($column->config['pattern'])) $pattern   = '-?\d+(\.\d+)?';
        if (!isset($column->config['max']))     $maxlength = 6;
        break;
}

print(<<<HTML
<input type="$type" step="any" name="ListEditable[{$classFQN}][$id][$column]" 
    id="Form-field-$class-$column" value="$valueEscaped" original="$valueEscaped"
    placeholder="$placeholder" class="form-control list-editable" autocomplete="off" 
    pattern="$pattern" maxlength="$maxlength" required="$required">
</input>
HTML);
