<?php

namespace Acorn\Backendlocalization\Class;

/**
 * Stub trait for backend localisation support.
 *
 * Full translation behaviour is provided by the optional
 * acornassociated/backendlocalization plugin. When that plugin is absent this
 * stub ensures models that use the trait still load correctly — all attribute
 * access is delegated to the parent class unchanged.
 */
trait TranslateBackend
{
    public function __get($name)
    {
        return parent::__get($name);
    }
}
