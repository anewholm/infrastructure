<?php namespace Acorn\Models;

use Acorn\Model;

class PhpInfo extends Model
{
    use \System\Traits\ViewMaker;
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var array Behaviors implemented by this model.
     */
    public $implement = [
        \System\Behaviors\SettingsModel::class,
        \Winter\Translate\Behaviors\TranslatableModel::class
    ];

    public $translatable = [];

    /**
     * @var string Unique code
     */
    public $settingsCode = 'acorn_phpinfo_settings';

    /**
     * @var mixed Settings form field definitions
     */
    public $settingsFields = 'fields.yaml';

    public $attachOne = [
    ];

    public $rules = [
    ];
}
