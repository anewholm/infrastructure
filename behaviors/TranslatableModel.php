<?php namespace Acorn\Behaviors;

use Exception;
use Winter\Translate\Behaviors\TranslatableModel as WinterTranslatableModel;
use Winter\Storm\Html\Helper as HtmlHelper;

class TranslatableModel extends WinterTranslatableModel
{
    public function setAttributeTranslated($key, $value, $locale = null)
    {
        // Set the translatableAttributes on the model, not the saveData
        // so when it saves, it will write the Winter\Translate\Attributes
        // TranslatableModel binds $model model.afterCreate
        //   => storeTranslatableBasicData()
        //     Db::table('winter_translate_attributes')->insert(translatableAttributes)
        if ($locale == null) {
            $locale = $this->translatableContext;
        }

        // 1-1 chain translation values saving
        // $this->model->attributes was having relationship arrays set on it, entity => [user_group => name] and crashing
        // Instead we ascertain the correct nested model
        // so it will set the translated names on university => entity => user_group, not on university
        $translatableModel = &$this;
        $model    = &$this->model;
        $keyArray = HtmlHelper::nameToArray($key);
        $isNested = (count($keyArray) > 1);
        if ($isNested) {
            // Remove the field [name] at the end, cause we only want the model
            $key = array_pop($keyArray);
            // Traverse to the real model
            // by reference, so it is used in $modelsToSave
            foreach ($keyArray as $step) {
                if (!$model->$step) {
                    // This is stolen from FormModelSaver::setModelAttributes()
                    // which will do this as well, during building of $modelsToSave
                    // We do it pre-imtively here to have a model for our translatableAttributes
                    $model->$step = $model->$step()->getRelated();
                }
                $newModel = &$model->$step;
                if (is_null($newModel)) {
                    $modelClass = get_class($model);
                    throw new Exception("TranslatableModel: $step did not exist on $modelClass");
                }
                $model = &$newModel;
            }
            $translatableModel = $model->getClassExtension('Acorn.Behaviors.TranslatableModel');
            if (!$translatableModel) $translatableModel = $model->getClassExtension('Winter.Translate.Behaviors.TranslatableModel');
        }
        
        // Set the attributes on the final model
        if ($locale == $this->translatableDefault) {
            // Set the name attribute directly
            $model->attributes[$key] = $value;
        } else {
            // Set translation data array values
            if (!array_key_exists($locale, $translatableModel->translatableAttributes)) {
                // This is our overridden function
                // that will pre-load all locale values
                $translatableModel->loadTranslatableData($locale);
            }
            $translatableModel->translatableAttributes[$locale][$key] = $value;
        }

        return $value;
    }

    public function getAttributeTranslated($key, $locale = null)
    {
        // Result should not return NULL to successfully hook beforeGetAttribute event
        $result = '';

        if ($locale == null) {
            $locale = $this->translatableContext;
        }

        // 1-1 chain translation values saving
        // $this->model->attributes was having relationship arrays set on it, entity => [user_group => name] and crashing
        // Instead we ascertain the correct nested model
        // so it will set the translated names on university => entity => user_group, not on university
        $translatableModel = &$this;
        $model    = &$this->model;
        $keyArray = HtmlHelper::nameToArray($key);
        $isNested = (count($keyArray) > 1);
        if ($isNested) {
            $key = array_pop($keyArray);
            foreach ($keyArray as $step) {
                // Careful with Create mode
                if ($model && $model->exists) $model = $model->{$step};
            }
            if ($model && $model->exists) {
                $translatableModel = $model->getClassExtension('Acorn.Behaviors.TranslatableModel');
                if (!$translatableModel) $translatableModel = $model->getClassExtension('Winter.Translate.Behaviors.TranslatableModel');
            }
        }

        if ($model && $model->exists) {
            // Default locale
            if (is_null($translatableModel) || $locale == $translatableModel->translatableDefault) {
                $result = $model->attributes[$key];
            }
            // Other locale
            else {
                if (!array_key_exists($locale, $translatableModel->translatableAttributes)) {
                    $translatableModel->loadTranslatableData($locale);
                }

                if ($translatableModel->hasTranslation($key, $locale)) {
                    $result = $translatableModel->translatableAttributes[$locale][$key];
                }
                elseif ($translatableModel->translatableUseFallback 
                    // Sometimes the translatable fields list is a lie
                    && isset($translatableModel->model->attributes[$key])
                ) {
                    $result = $translatableModel->model->attributes[$key];
                }
            }

            // Handle jsonable attributes, default locale may return the value as a string
            if (
                is_string($result) &&
                method_exists($model, 'isJsonable') &&
                $model->isJsonable($key)
            ) {
                $result = json_decode($result, true);
            }
        }

        return $result;
    }

    protected function loadTranslatableData($locale = null)
    {
        // This is copied from the parent method
        // with a manual relation query below
        if (!$locale) {
            // The current Lang::getLocale()
            $locale = $this->translatableContext;
        }

        $result = [];
        $this->translatableAttributes[$locale] = $result;

        if ($this->model->exists) {
            // If running within a noConstraints() callback
            // like makeRenderFormField() for simple form fields like dropdowns
            // then all translations will be loaded everytime
            // addConstraints() will have no effect due to the static::$constraints == FALSE in RelationBase
            //
            // So we manually addConstraints() of the winter attributes table request
            // Pre-load all locales for this model
            $translationsRelation = $this->model->translations()
                ->where('model_type', get_class($this->model))
                ->where('model_id',   $this->model->id);
                // ->where('locale',     $locale)
            $translationsRelation->get()->each(function($attributesDetails) use($locale, &$result) {
                $thisLocale    = $attributesDetails->locale;
                $attributeData = json_decode($attributesDetails->attribute_data, true);
                $this->translatableOriginals[$thisLocale]  = $attributeData;
                $this->translatableAttributes[$thisLocale] = $attributeData;
                if ($thisLocale == $locale) $result = $attributeData;
            });
        }

        return $result;
    }
}
