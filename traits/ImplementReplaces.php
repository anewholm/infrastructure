<?php namespace Acorn\Traits;

Trait ImplementReplaces {
    public function isClassExtendedWith($name) {
        // Winter hard codes behavior requirements sometimes
        // for example: 
        //   EventRegistry::registerModelTranslation() 
        //   requires Winter.Translate.Behaviors.TranslatableModel
        // Here we fake the implemntation in favor of our sub-class
        return parent::isClassExtendedWith($name)
            || (
                isset($this->implementReplaces) 
                && in_array($name, $this->implementReplaces)
            );
    }
}
