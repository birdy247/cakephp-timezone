<?php
/**
 * Created by PhpStorm.
 * User: xncreations_aaron
 * Date: 18/09/2017
 * Time: 08:43
 */

namespace TimezoneAware\Database\Type;

use Cake\Database\Type\DateTimeType;
use Cake\Log\Log;

class TimezoneAwareDateTimeType extends DateTimeType
{
    const TARGET = 'UTC';

    /**
     * The current server time.
     *
     * @var string
     */
    protected $_currentTimezone;

    /**
     * The timezone the user is inputting from
     *
     * @var
     */
    protected $_sourceTimezone;

    /**
     * {@inheritDoc}
     */
    public function __construct($name = null)
    {
         //Somehow need to get the "Source".
        parent::__construct($name);

        //Capture the current server timezone so we know what to reset it to
        $this->_currentTimezone = date_default_timezone_get();

        //Use the local parser
        $this->useLocaleParser(true);
    }

    /**
     * Returns the base type name that this class is inheriting.
     * This is useful when extending base type for adding extra functionality
     * but still want the rest of the framework to use the same assumptions it would
     * do about the base type it inherits from.
     *
     * @return string
     */
    public function getBaseType()
    {
        return 'datetime';
    }

    /**
     * Convert request data into a datetime object.
     *
     * @param mixed $value Request data
     * @return \Cake\I18n\Time
     */
    public function marshal($value)
    {
        $datetime =  isset($value['date']) ? $value['date'] : "01/01/2017 00:00"; //TODO hack
        $this->sourceTimezone = isset($value['timezone']) ? $value['timezone'] : 'Europe/London';

        date_default_timezone_set($this->sourceTimezone);
        if ($datetime instanceof \DateTime) {
            $datetime->setTimezone(new \DateTimeZone(self::TARGET));
            $this->_resetTimezone();
            return $datetime;
        }

        if ($datetime instanceof \DateTimeImmutable) {
            $new = $datetime->setTimezone(new \DateTimeZone(self::TARGET));
            $this->_resetTimezone();
            return $new;
        }

        $class = $this->_className;

        try {
            $compare = $date = false;
            if ($datetime === '' || $datetime === null || $datetime === false || $datetime === true) {
                $this->_resetTimezone();
                return null;
            } elseif (is_numeric($datetime)) {
                $date = new $class('@' . $datetime);
                $date->timezone(new \DateTimeZone(self::TARGET));
            } elseif (is_string($datetime) && $this->_useLocaleParser) {
                return $this->_parseValue($datetime);
            } elseif (is_string($datetime)) {
                $date = new $class($datetime, new \DateTimeZone(self::TARGET));
                $compare = true;
            }
            if ($compare && $date && $date->format($this->_format) !== $datetime) {
                $this->_resetTimezone();
                return $datetime;
            }
            if ($date) {
                $this->_resetTimezone();
                return $date;
            }
        } catch (\Exception $e) {
            $this->_resetTimezone();
            return $datetime;
        }
        if (is_array($datetime) && implode('', $datetime) === '') {
            $this->_resetTimezone();
            return null;
        }

        $datetime += ['hour' => 0, 'minute' => 0, 'second' => 0];
        $format = '';
        if (isset($datetime['year'], $datetime['month'], $datetime['day']) &&
            (is_numeric($datetime['year']) && is_numeric($datetime['month']) && is_numeric($datetime['day']))
        ) {
            $format .= sprintf('%d-%02d-%02d', $datetime['year'], $datetime['month'], $datetime['day']);
        }
        if (isset($datetime['meridian'])) {
            $datetime['hour'] = strtolower($datetime['meridian']) === 'am' ? $datetime['hour'] : $datetime['hour'] + 12;
        }
        $format .= sprintf(
            '%s%02d:%02d:%02d', empty($format) ? '' : ' ', $datetime['hour'], $datetime['minute'], $datetime['second']
        );
        $date = new $class($format, new \DateTimeZone($this->sourceTimezone));
        $date = $date->setTimezone(new \DateTimeZone(self::TARGET));
        $this->_resetTimezone();
        return $date;
    }

    /**
     * Converts a string into a DateTime object after parseing it using the locale
     * aware parser with the specified format.
     *
     * @param string $value The value to parse and convert to an object.
     * @return \Cake\I18n\Time|null
     */
    protected function _parseValue($value)
    {
        date_default_timezone_set($this->sourceTimezone);
        $value = parent::_parseValue($value);
        $new = $value->timezone(self::TARGET);
        $this->_resetTimezone();
        return $new;
    }

    /**
     *  Reset server timezone before we started adjusting it
     */
    protected function _resetTimezone()
    {
        date_default_timezone_set($this->_currentTimezone);
    }
}