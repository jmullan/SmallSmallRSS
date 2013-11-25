<?php
namespace SmallSmallRSS;

class Utils
{
    public static function stripslashesDeep($value)
    {
        $value = (
            is_array($value)
            ? array_map(array(self, 'stripslashesDeep'), $value)
            : stripslashes($value));
        return $value;
    }

    public static function cleanUpStripslashes()
    {
        static $finished = false;
        if (!$finished) {
            if (get_magic_quotes_gpc()) {
                $_POST = array_map(array(self, 'stripslashesDeep'), $_POST);
                $_GET = array_map(array(self, 'stripslashesDeep'), $_GET);
                $_COOKIE = array_map(array(self, 'stripslashesDeep'), $_COOKIE);
                $_REQUEST = array_map(array(self, 'stripslashesDeep'), $_REQUEST);
            }
            $finished = true;
        }
    }

    public static function smallSmallAutoload($class)
    {
        $class_file = str_replace("_", "/", strtolower(basename($class)));
        $file = __DIR__ . "/../classes/$class_file.php";
        if (file_exists($file)) {
            require $file;
        }
    }
}
