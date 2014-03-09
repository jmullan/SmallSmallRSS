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
        $result = array(
            'error' => array(
                'code' => $this->error_code->getOrdinal(),
                'message' => $this->error_code->description()
            )
        );
        print json_encode($result);
    }
}
