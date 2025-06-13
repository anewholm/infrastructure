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

$attributes = NULL;
switch ($type) {
    case 'number':
        if (!isset($column->config['pattern'])) $pattern   = '-?\d+(\.\d+)?';
        if (!isset($column->config['max']))     $maxlength = 6;
        break;
    case 'boolean':
        $type       = 'checkbox';
        $attributes = ($value ? 'checked' : '');
        break;
}

print('<div class="list-editable-container">');
if ($titleEscaped) print(<<<HTML
    <label for="$idValue">$titleEscaped</label>
HTML);

if ($readOnly) {
    print(<<<HTML
        <div id="$idValue" class="form-control list-editable read-only">$valueEscaped</div>
HTML
    );
} else {
    print(<<<HTML
    <input type="$type" step="any" name="{$nameStem}[$columnName]" 
        id="$idValue" value="$valueEscaped" original="$valueEscaped"
        placeholder="$placeholder" class="form-control list-editable list-editable-$type" autocomplete="off" 
        pattern="$pattern" maxlength="$maxlength" required="$required" $attributes>
    </input>
HTML
    );

    foreach ($createValues as $name => $value) {
        $valueEscaped = e($value);
        print("<input type='hidden' name='{$nameStem}[$name]' value='$valueEscaped'></input>");
    }
}

print('</div>');
