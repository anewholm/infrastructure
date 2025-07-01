<?php
$classFQN     = get_class($record);
$classParts   = explode('\\', $classFQN);
$class        = end($classParts);
$columnName   = $column->getName();
$maxlength    = (isset($column->config['max'])           ? $column->config['max']           : 255 );
$type         = (isset($column->config['typeEditable'])  ? $column->config['typeEditable']  : 'number' );
$required     = (isset($column->config['required'])      ? $column->config['required']      : FALSE );
$placeholder  = (isset($column->config['placeholder'])   ? $column->config['placeholder']   : '' );
$pattern      = (isset($column->config['pattern'])       ? $column->config['pattern']       : '' );
$readOnly     = (isset($column->config['readOnly'])      ? $column->config['readOnly']      : FALSE );
$id           = $record->id;
$createValues = (isset($createValues) ? $createValues : array());
$values       = (isset($values)       ? $values : array());
$idValue      = "Form-field-$class-$columnName";
$nameStem     = "ListEditable[{$classFQN}][$id]";
$locale       = Lang::getLocale();
$localeFallback = Lang::getFallback();

// Title can be a locale array
$titleEscaped = NULL;
if (isset($title)) {
    if (is_array($title)) {
        if      (isset($title[$locale]))         $title = $title[$locale];
        else if (isset($title[$localeFallback])) $title = $title[$localeFallback];
        else $title = '';
    }
    $titleEscaped = e($title);
}

// Value can be a locale array
$valueEscaped = NULL;
if (isset($value)) {
    if (is_array($value)) {
        if      (isset($value[$locale]))         $value = $value[$locale];
        else if (isset($value[$localeFallback])) $value = $value[$localeFallback];
        else $value = '';
    }
    $valueEscaped = e($value);
}

$checked = NULL;
$inputType = 'number';
switch ($type) {
    case 'double precision':
    case 'integer':
    case 'number':
        if (!isset($column->config['pattern'])) $pattern   = '-?\d+(\.\d+)?';
        if (!isset($column->config['max']))     $maxlength = 6;
        break;
    case 'boolean':
        $inputType = 'checkbox';
        $checked   = ($value ? 'checked' : '');
        break;
}

$readOnlyClass = ($readOnly ? 'read-only' : '');
$disabled      = ($readOnly ? 'disabled'  : '');

print('<div class="list-editable-container">');

if ($titleEscaped) print(<<<HTML
    <label for="$idValue">$titleEscaped</label>
HTML);

print(<<<HTML
<input type="$inputType" step="any" name="{$nameStem}[$columnName]" 
    id="$idValue" value="$valueEscaped" original="$valueEscaped"
    placeholder="$placeholder" class="form-control list-editable list-editable-$type $readOnlyClass" autocomplete="off" 
    pattern="$pattern" maxlength="$maxlength" required="$required" $disabled $checked>
</input>
HTML
);

// Accompanying values for not-NULL create row scenarios
// like score rows
foreach ($createValues as $name => $value) {
    $valueEscaped = e($value);
    print("<input type='hidden' name='{$nameStem}[$name]' value='$valueEscaped'></input>");
}

// Other values
foreach ($values as $name => $value) {
    switch ($name) {
        case 'id':
        case 'title':
        case 'value':
        case 'createValues':
            // Special handling
            break;
        default:
            $valueEscaped = e($value);
            // TODO: Extra value handling?
            print("<input type='hidden' class='list-editable-extra-value-$type-$name' value='$valueEscaped'></input>");
    }
}

print('</div>');
