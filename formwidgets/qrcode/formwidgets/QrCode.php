<?php namespace Acorn\QrCode\FormWidgets;

use Backend\Classes\FormWidgetBase;
use SimpleSoftwareIO\QrCode\Facades\QrCode as SimpleSoftwareQrCode;
use Illuminate\Support\Str;
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
        $config = $this->formField->config;
        $size = isset($config['size']) && is_numeric($config['size']) ? (int)$config['size'] : $this->size;
        return self::generateQrCode($this->model, $size);
    }

    /**
     * Generates the QR code as a string
     *
     * @param Model $model
     * @param int $size
     * @return string
     */
    public static function generateQrCode(Model $model, $size)
    {
        $size = is_numeric($size) ? (int)$size : 96; // Default size if null or not numeric

        $url = self::URLToObjectDetails($model);
        return SimpleSoftwareQrCode::size($size)->generate($url);
    }

    /**
     * Prepares the URL for the QR code
     *
     * @param Model $model
     * @return string
     */
    public static function URLToObjectDetails(Model $model)
    {
        $class = get_class($model);
        $id = $model->id();
        $info   = explode('\\', $class);
        $author = $info[0];
        $plugin = $info[1];
        $modelName = $info[3];
        $author = strtolower($author);
        $plugin = strtolower($plugin);
        $modelName = strtolower(Str::plural($modelName));

        $baseUrl = config('app.url'); // Ensure base URL is set in config &&  maybe we can use url() function

        return "{$baseUrl}/backend/{$author}/{$plugin}/{$modelName}/update/{$id}";
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        return $value;
    }
}
