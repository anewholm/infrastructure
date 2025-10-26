<?php
// This is the translations morphMany relation (programatically added) on the TranslateableModel / TranslatableBehavior class
// translations:
//   relation: translations
//   type: partial
//   path: translations
$model    = (isset($record) ? $record : $model);
$nameOnly = TRUE;
$currentLocale = Lang::getLocale();

// From fields.yaml: array of Attribute IDs
if (is_array($value)) {
    $value = \Winter\Translate\Models\Attribute::whereIn('id', $value)->get();
}

// From columns.yaml: Collection of Winter\Translate\Models\Attribute
if (is_null($value)) {
    // Forms do this
    $noRecords = e(trans('backend::lang.list.no_records'));
    print("<p class='no-data'>$noRecords</p>");
} else if ($value instanceof \Winter\Storm\Database\Collection) {
    // Collection in columns.yaml
    if ($value->isEmpty()) {
        // Columns report here
        print('-');
    } else {
        // Re-organise in to attribute name first, then locale,
        // ignoring empty values
        // In effect, description will be ignored
        $attributesArray = array();
        foreach ($value as $object) {
            $attributesLocale = json_decode($object->attribute_data);
            foreach ($attributesLocale as $attributeName => $value) {
                if ($value && (!$nameOnly || $attributeName == 'name')) {
                    // If no descriptions set, there will be no details
                    if (!isset($attributesArray[$attributeName])) $attributesArray[$attributeName] = array();
                    $attributesArray[$attributeName][$object->locale] = $value;
                }
            }
        }

        // Add English
        // Only to those with existing translations
        foreach ($attributesArray as $attributeName => $locales) {   
            if (isset($model->$attributeName)) {
                $attributesArray[$attributeName]['en'] = $model->$attributeName;
            }
        }

        // Output 1 or many (name, description, ...) translations
        $multiple = (count($attributesArray) > 1);
        foreach ($attributesArray as $attributeName => $locales) {
            if ($multiple) {
                $attributeTitleEscaped = e(Str::title($attributeName));
                print("<label>$attributeTitleEscaped</label>");
            }
            print("<ul class='translations translations-$attributeName'>");
            foreach ($locales as $locale => $value) {
                if (strlen($value) > 20) 
                    $value = substr($value, 0, 20) . '...';
                $valueEscaped = e($value);

                $isCurrent = ($locale == $currentLocale ? 'current' : '');
                $localName = $locale;
                switch ($locale) {
                    case 'en': $localName = 'English'; break;
                    case 'ku': $localName = 'Kurdî'; break;
                    case 'ar': $localName = 'Erebî'; break;
                }
                
                print("<li class='$isCurrent'><label>$localName</label>: $valueEscaped</li>");
            }
            print("</ul>");
        }
    }
} else {
    // Unknown
    if (env('APP_DEBUG')) print('<span class="warning">Translations is not a Collection</span>');
}