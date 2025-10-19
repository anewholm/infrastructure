<?php
$classFQN     = get_class($record);
$classParts   = explode('\\', $classFQN);
$class        = end($classParts);
$columnName   = $column->getName();
$maxlength    = (isset($column->config['max'])           ? $column->config['max']           : 255 );
$type         = (isset($column->config['typeEditable'])  ? $column->config['typeEditable']  : 'number' );
$on           = (isset($column->config['on'])            ? $column->config['on']            : 'backend::lang.form.field_on' );
$off          = (isset($column->config['off'])           ? $column->config['off']           : 'backend::lang.form.field_off' );
if (!isset($required)) 
    $required = (isset($column->config['required'])      ? $column->config['required']      : FALSE );
$passed       = (isset($passed) ? $passed : NULL);
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
    $typeKey = "title_type";
    if (!is_array($title) && is_array($values) && isset($values[$typeKey])) {
        // TODO: Use $morphsTo = ['value'] comment on view
        $titleType = $values[$typeKey];
        $titleObj  = $titleType::find($title);
        $title     = $titleObj->getAttributeTranslated('name', $locale);
    } else {
        if (is_array($title)) {
            if      (isset($title[$locale]))         $title = $title[$locale];
            else if (isset($title[$localeFallback])) $title = $title[$localeFallback];
            else $title = '';
        }
    }
    $titleEscaped = e($title);
}

// Value can be a locale array
$valueEscaped = NULL;
if (isset($value)) {
    $typeKey = "value_type";
    if (!is_array($value) && is_array($values) && isset($values[$typeKey])) {
        // TODO: Use $morphsTo = ['value'] comment on view
        $valueType = $values[$typeKey];
        $valueObj  = $valueType::find($value);
        $value     = $valueObj->getAttributeTranslated('name', $locale);
    } else {
        if (is_array($value)) {
            if      (isset($value[$locale]))         $value = $value[$locale];
            else if (isset($value[$localeFallback])) $value = $value[$localeFallback];
            else $value = '';
        }
    }
    $valueEscaped = e($value);
}

$valueSuffixEscaped = NULL;
if (is_array($values) && isset($values['value_suffix'])) {
    $valueSuffix = $values['value_suffix'];
    $typeKey     = "value_suffix_type";
    if (isset($values[$typeKey])) {
        // TODO: Use $morphsTo = ['value'] comment on view
        $valueSuffixType = $values[$typeKey];
        $valueSuffixObj  = $valueSuffixType::find($valueSuffix);
        $valueSuffix     = $valueSuffixObj->getAttributeTranslated('name', $locale);
    } else {
        if (is_array($valueSuffix)) {
            if      (isset($valueSuffix[$locale]))         $valueSuffix = $valueSuffix[$locale];
            else if (isset($valueSuffix[$localeFallback])) $valueSuffix = $valueSuffix[$localeFallback];
            else $valueSuffix = '';
        }
    }
    $valueSuffixEscaped = e($valueSuffix);
    $valueEscaped .= " $valueSuffixEscaped";
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
    case 'string':
        $inputType = 'text';
}

$readOnlyClass = ($readOnly ? 'read-only' : '');
$disabled      = ($readOnly ? 'disabled'  : '');
$isRequired    = ($required ? 'is-required' : '');
$isPassed      = (is_null($passed) ? '' : ($passed ? 'passed' : 'failed')); // 3-state

print("<div class='list-editable-container $isRequired $isPassed'>");
if ($titleEscaped) print(<<<HTML
    <label for="$idValue">$titleEscaped</label>
HTML);

switch ($inputType) {
    case 'checkbox':
        $onEscaped    = e(trans($on));
        $offEscaped   = e(trans($off));
        $checked      = ($value ? 'checked="1"' : ''); 

        print(<<<HTML
        <div class="list-editable">
            <input type="hidden" name="{$nameStem}[$columnName]" value="0" autocomplete="off">
            <label class="custom-switch">
                <input $checked original="$valueEscaped" type="checkbox" id="$idValue" name="{$nameStem}[$columnName]" value="1" autocomplete="off">
                <span>
                    <span>$onEscaped</span>
                    <span>$offEscaped</span>
                </span>
                <a class="slide-button"></a>
            </label>
        </div>
HTML
        );
        break;
    default:
        print(<<<HTML
        <input type="$inputType" step="any" name="{$nameStem}[$columnName]" 
            id="$idValue" value="$valueEscaped" original="$valueEscaped"
            placeholder="$placeholder" class="form-control list-editable list-editable-$type $readOnlyClass" autocomplete="off" 
            pattern="$pattern" maxlength="$maxlength" required="$required" $disabled $checked>
        </input>
HTML
        );
}

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
        case 'type':
        case 'title':
        case 'title_type':
        case 'value':
        case 'value_type':
        case 'value_suffix':
        case 'value_suffix_type':
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
