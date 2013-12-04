<?php
namespace SmallSmallRSS\Handlers;
class Handler implements IHandler
{
    protected $dbh;
    protected $args;

    public function __construct($args)
    {
        $this->args = $args;
    }

    public function ignoreCSRF($method)
    {
        return true;
    }

    public function before($method)
    {
        return true;
    }

    public function after()
    {
        return true;
    }

    public static function getBooleanFromRequest($key)
    {
        $value = false;
        if (isset($_REQUEST[$key])) {
            $value = $_REQUEST[$key];
        }
        if ($value === true || $value === 1) {
            return true;
        } elseif ($value === false || $value === 0) {
            return false;
        }
        if (is_string($value)) {
            $value = strtolower($value);
            if ($value == 't' || $value == 'true' || $value == 'y' || $value == 'yes' || 'value' == 'on') {
                return true;
            } else {
                return false;
            }
        }
        return $value;
    }

    public static function getSQLEscapedStringFromRequest($key)
    {
        $value = '';
        if (isset($_REQUEST[$key])) {
            $value = \SmallSmallRSS\Database::escape_string($_REQUEST[$key]);
        }
        return $value;
    }

}
