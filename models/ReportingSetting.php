<?php namespace Acorn\Models;

use App;
use Backend;
use Url;
use File;
use Lang;
use Model;
use Cache;
use Config;
use Less_Parser;
use Exception;

/**
 * Brand settings that affect all users
 *
 * @package winter\wn-backend-module
 * @author Alexey Bobkov, Samuel Georges
 * @author Winter CMS
 */
class ReportingSetting extends Model
{
    use \System\Traits\ViewMaker;
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var array Behaviors implemented by this model.
     */
    public $implement = [
        \System\Behaviors\SettingsModel::class
    ];

    /**
     * @var string Unique code
     */
    public $settingsCode = 'acorn_reporting_settings';

    /**
     * @var mixed Settings form field definitions
     */
    public $settingsFields = 'fields.yaml';

    public $attachOne = [
    ];

    public $rules = [
    ];
}
