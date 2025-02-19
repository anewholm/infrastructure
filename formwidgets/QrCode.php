<?php namespace Acorn\FormWidgets;

use Backend\Classes\FormWidgetBase;
use Acorn\Model;

/**
 * QRManager for generate and scan
 *
 * @package Acorn\QrCode
 * @author Acorn
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
        return $this->makePartial('~/modules/acorn/partials/_qrcode');
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
        $this->addCss('/modules/acorn/assets/css/forms.css');
        $this->addCss('/modules/acorn/assets/css/qrcode-printing.css');
        $this->addCss('/modules/acorn/assets/css/html5-qrcode.css');
        $this->addJs('/modules/acorn/assets/js/findbyqrcode.js');
        $this->addJs('/modules/acorn/assets/js/html5-qrcode.js');
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        return $value;
    }
}
