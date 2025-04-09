<?php namespace AcornAssociated\FormWidgets;

use Backend\Classes\FormWidgetBase;
use AcornAssociated\Model;

/**
 * QRManager for generate and scan
 *
 * @package Acornassociated\QrCode
 * @author Acornassociated
 */

class QrCode extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'qrcode';

    /**
     * @inheritDoc
     */
    public function widgetDetails()
    {
        return [
            'name'        => 'QrCode',
            'description' => 'Displays a QrCode Generate widget'
        ];
    }

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->fillFromConfig(['size']);

        if ($this->size === null || !is_numeric($this->size)) {
            $this->size = 96; // Default size
        }
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('~/modules/acornassociated/partials/_qrcode');
    }

    /**
     * Prepares the form widget view data
    */
    public function prepareVars()
    {
        $this->vars['formModel'] = $this->model;
        $this->vars['formField'] = $this->formField;
    }

    /**
     * @inheritDoc
     */
    public function loadAssets()
    {
        $this->addCss('/modules/acornassociated/assets/css/forms.css');
        $this->addCss('/modules/acornassociated/assets/css/qrcode-printing.css');
        $this->addCss('/modules/acornassociated/assets/css/html5-qrcode.css');
        $this->addJs('/modules/acornassociated/assets/js/findbyqrcode.js');
        $this->addJs('/modules/acornassociated/assets/js/html5-qrcode.js');
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        return $value;
    }
}
