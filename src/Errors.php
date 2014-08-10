<?php
namespace SmallSmallRSS;

class Errors extends \MabeEnum\Enum
{
    const UNSPECIFIED = 0;
    const REQUIRES_XHR = 1;
    const REQUIRES_COOKIES = 2;
    const BACKEND_INSANE = 3;
    const FRONTEND_INSANE = 4;
    const INCORRECT_DB_SCHEMA = 5;
    const REQUEST_UNAUTHORIZED = 6;
    const NOTHING_TO_DO = 7;
    const ACCESS_DENIED = 8;
    const BAD_CONFIG = 9;
    const OLD_MYSQL = 10;
    const NON_SERVER_ERROR = 11;
    const SQL_ESCAPE_FAILED = 12;

    private $description = null;

    public static function dump()
    {
        $ERRORS = array(
            self::UNSPECIFIED => __('No error'),
            self::REQUIRES_XHR => __(
                'This program requires XmlHttpRequest'
                . " to function properly. Your browser doesn't seem to support it."
            ),
            self::REQUIRES_COOKIES => __(
                'This program requires cookies'
                . " to function properly. Your browser doesn't seem to support them."
            ),
            self::BACKEND_INSANE => __('Backend sanity check failed.'),
            self::FRONTEND_INSANE => __('Frontend sanity check failed.'),
            self::INCORRECT_DB_SCHEMA => __(
                "Incorrect database schema version. &lt,a href='db-updater.php'&gt,"
                . ' Please update&lt,/a&gt,.'
            ),
            self::REQUEST_UNAUTHORIZED => __('Request not authorized.'),
            self::NOTHING_TO_DO => __('No operation to perform.'),
            self::ACCESS_DENIED => __('Denied. Your access level is insufficient to access this page.'),
            self::BAD_CONFIG => __('Configuration check failed'),
            self::OLD_MYSQL => __(
                'Your version of MySQL is not currently supported. Please see official'
                . ' site for more information.'
            ),
            self::NON_SERVER_ERROR => '[This error is not returned by server]',
            self::SQL_ESCAPE_FAILED => __(
                'SQL escaping test failed, check your database and PHP configuration'
            )
        );
        return $ERRORS;
    }

    public function description()
    {
        $errors = self::dump();
        return $errors[$this->getOrdinal()];
    }
}
