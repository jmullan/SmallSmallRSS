<?php
namespace SmallSmallRSS\Renderers;
class JSONError extends \SmallSmallRSS\Renderers\Base
{

    public static $error_code;

    public function __construct($error_code)
    {
        $this->error_code = $error_code;
    }
    public function render()
    {
        header('Content-Type: application/json');
        print json_encode(array('error' => array('code' => $this->error_code)));
    }
}
