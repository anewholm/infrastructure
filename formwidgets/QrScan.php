<?php namespace AcornAssociated\FormWidgets;

use Backend\Classes\FormWidgetBase;

/**
 * QRManager for genrate and scan 
 *
 * @package Acornassociated\QrCode
 * @author Acornassociated
 */
class QrScan extends FormWidgetBase
{
    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'qrscan';

    /**
     * @inheritDoc
     */
    public function widgetDetails()
    {
        return [
            'name'        => 'QR  Scanner',
            'description' => 'Displays a QR  scanner widget'
        ];
    }

    /**
     * @inheritDoc
     */
    public function render()
    {   
        $this->prepareVars();
        return $this->makePartial('~/modules/acornassociated/partials/_qrscan');
    }

    /**
     * Prepares the form widget view data
    */
    public function prepareVars()
    {
        $this->vars['name'] = $this->formField->getName();
        $this->vars['value'] = e($this->getLoadValue());
        $this->vars['formModel'] = $this->model;
        $this->vars['formField'] = $this->formField;
        $this->vars['actions'] = $this->formField->getConfig('actions', ['form-field-complete']);
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
