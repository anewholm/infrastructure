<?php namespace Acorn;

use Carbon\Carbon;
use Lang;
use \IntlDateFormatter; // PHP install option: sudo apt-get install -y php-intl
use Exception;
use InvalidArgumentException;
use Config;

class IntlCarbon extends Carbon {
    const TEXT_FORMATS = 'DlSFMaAe';

    // Switch this to stop translation
    // Useful for DB dates
    public $intl = TRUE;

    public static function make($value, $throwException = TRUE)
    {
        // Copied/adjusted from System\Helpers\DateTime::makeCarbon()
        if ($value instanceof IntlCarbon) {
            // Do nothing
        }
        elseif ($value instanceof Carbon) {
            $value = new IntlCarbon($value);
        }
        elseif ($value instanceof PhpDateTime) {
            $value = IntlCarbon::instance($value);
        }
        elseif (is_numeric($value)) {
            $value = IntlCarbon::createFromTimestamp($value);
        }
        elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            $value = IntlCarbon::createFromFormat('Y-m-d', $value)->startOfDay();
        }
        else {
            try {
                $value = IntlCarbon::parse($value);
            } catch (Exception $ex) {
                // Try one last time to parse the date in case periods were used
                try {
                    $value = IntlCarbon::parse(str_replace('.', '/', $value));
                } catch (Exception $ex2) {
                }
            }
        }

        if (!$value instanceof IntlCarbon && $throwException) {
            throw new InvalidArgumentException('Invalid date value supplied to DateTime helper.');
        }
        
        // Copied/adjusted from Backend::makeCarbon()
        // TODO: This is application specific and should not be here
        // Not sure where else to put it yet...
        try {
            // Find user preference
            $value->setTimezone(\Backend\Models\Preference::get('timezone'));
        } catch (Exception $ex) {
            // Use system default
            $value->setTimezone(Config::get('cms.backendTimezone', Config::get('app.timezone')));
        }

        return $value;
    }

    public function forDB(): Carbon
    {
        return new Carbon($this->toDateTime());
    }

    public function formatSingleNumeric($format): int|bool
    {
        $str = parent::format($format);
        return (is_bool($str) ? $str : (int) $str);
    }

    public function formatForDB($format = 'Y-m-d H:i:s'): string|bool
    {
        return parent::format($format);
    }

    public static function convertStandardFormatToIntlDateFormatter(string $format): string
    {
        // IntlDateFormatter uses a *different* date format specifications:
        // https://unicode-org.github.io/icu/userguide/format_parse/datetime/
        // Translation of PHP DateTime formats to IntlDateFormatter formats
        // Date
        $format = str_replace('d', 'dd', $format); // DOM, 01
        $format = str_replace('j', 'd', $format); // DOM, 1
        $format = str_replace('l', 'EEEE', $format); // Long day, Monday
        $format = str_replace('D', 'E', $format); // Short day, Mon
        $format = str_replace('N', 'e', $format); // Day in week, 1-7
        // TODO: S => 1st, 2nd, 3th
        $format = str_replace('S', '', $format); // Ordinal text: 1st, 2nd, 3th
        $format = str_replace('w', 'c', $format); // Day in week, 1-7
        $format = str_replace('M', 'MMM', $format); // Month, Jan
        $format = str_replace('F', 'MMMM', $format); // Month, January
        $format = str_replace('m', 'MM', $format); // Month, 01
        $format = str_replace('n', 'M', $format); // Month, 1
        // Time
        $format = str_replace('H', 'HH', $format); // 24-Hour, 09
        $format = str_replace('i', 'mm', $format); // Minute, 05
        $format = str_replace('s', 'ss', $format); // Second, 05
        return $format;
    }

    public function format($format): string|bool
    {
        // https://www.php.net/manual/en/intldateformatter.format.php
        $locale = Lang::getLocale();
        if ($locale == 'en' || !$this->intl || !class_exists('\IntlDateFormatter')) {
            // This is for the DB and we do not want to confuse things
            $str = parent::format($format);
        } 
        // An assumption
        // that if the application is asking for this
        // probably it is programmatic, not display
        // Add a space to the format to prevent this
        else if (in_array($format, array('w', 'm', 'd', 'n'))) {
            $str = (string) $this->formatSingleNumeric($format);
        } 
        // An assumption
        // That this format is for the database
        else if ($format == 'Y-m-d') {
            $str = parent::format($format);
        }
        else {
            $format = self::convertStandardFormatToIntlDateFormatter($format);
            $fmt = new IntlDateFormatter(
                locale:   $locale,
                dateType: IntlDateFormatter::FULL,
                timeType: IntlDateFormatter::FULL,
                pattern:  $format
            );
            $str = $fmt->format($this);
        }

        return $str;
    }
}