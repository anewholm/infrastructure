<?php namespace Acorn\QrCode\FormWidgets;

use Backend\Classes\FormWidgetBase;


/**
 * QRManager for genrate and scan
 *
 * @package Acorn\QrCode
 * @author Acorn
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
        $name = $this->getFieldName();
        $value = e($this->getLoadValue());

        return  '<div id="my-qr-reader"></div>
        <input  class="form-control"  type="hidden" name="'. $name .'" value="' . $value . '"></input>';
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        return $value;
    }
}
