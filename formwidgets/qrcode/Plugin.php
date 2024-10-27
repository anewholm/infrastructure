<?php

namespace Acorn\QrCode;

use Backend;
use System\Classes\PluginBase;
use SimpleSoftwareIO\QrCode\Facades\QrCode as SimpleSoftwareQrCode;
use Acorn\QrCode\FormWidgets\QrCode;

/** TODO: Move this in to the AA ServiceProvider
 * QRManager for generate and scan
 *
 * @package Acorn\QrCode
 * @author Acorn
 */
class Plugin extends PluginBase
{

    use \System\Traits\AssetMaker;

    /**
     * Registers any ColumnTypes implemented in this plugin.
     */
    public function registerListColumnTypes()
    {
        return [
            'qrcode' => [$this, 'evalQrCodeListColumn']
        ];
    }

    /**
     * evalfunction for columen
     */
    public function evalQrCodeListColumn($model,$column, $record)
    {
        $size  = isset($column->config['size']) && is_numeric($column->config['size']) ? (int)$column->config['size'] : 96;
        $url = QrCode::URLToObjectDetails($record); // Use the same method as in the FormWidget

        return SimpleSoftwareQrCode::size($size)->generate($url);
    }

    /**
     * Registers any formFildesWidget  implemented in this plugin.
    */
    public function registerFormWidgets()
    {
        return [
            'Acorn\QrCode\FormWidgets\QrScan' =>
            [
                'label' => 'QR Scan Field',
                'code' => 'qrscan'
            ],
            'Acorn\QrCode\FormWidgets\QrCode' =>
            [
                'label' => 'QR Generate Field',
                'code' => 'qrcode'
            ],
        ];
    }
}
