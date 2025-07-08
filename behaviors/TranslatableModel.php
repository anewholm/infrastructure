<?php namespace Acorn\Behaviors;

use Winter\Translate\Behaviors\TranslatableModel as WinterTranslatableModel;
use Winter\Storm\Html\Helper as HtmlHelper;

class TranslatableModel extends WinterTranslatableModel
{
    public function setAttributeTranslated($key, $value, $locale = null)
    {
        if ($locale == null) {
            $locale = $this->translatableContext;
        }

        if ($locale == $this->translatableDefault) {
            // $this->model->attributes is having relationship arrays set on it, entity => [user_group => name]
            // It is pass-by-reference
            // Instead we send the whole model, so that relationships are correctly traversed
            return $this->setAttributeFromData($this->model, $key, $value);
        }

        if (!array_key_exists($locale, $this->translatableAttributes)) {
            $this->loadTranslatableData($locale);
        }

        return $this->setAttributeFromData($this->translatableAttributes[$locale], $key, $value);
    }

    protected function setAttributeFromData(&$dataOrModel, $attribute, $value)
    {
        $keyArray = HtmlHelper::nameToArray($attribute);

        if (is_array($dataOrModel)) {
            // Sometimes direct &arrays are sent also (2 method references ^)
            array_set($dataOrModel, implode('.', $keyArray), $value);
        } else {
            // This is to accomodate &Models
            // and setting of nested relationships values
            // rather than direct attributes
            // setAttributeTranslated() has been changed to send the &Model, not &Model->attributes
            $name = array_pop($keyArray);
            foreach ($keyArray as $step) {
                $dataOrModel = &$dataOrModel->{$step};
            }
            $dataOrModel->{$name} = $value;
        }

        return $value;
    }
}
