<?php
namespace SmallSmallRSS\Renderers;
class JSONError extends \SmallSmallRSS\Renderers\Base {

    static public $error_code;

    public function __construct($error_code) {
        $this->error_code = $error_code;
    }
    public function render() {
        header("Content-Type: text/json");
        print json_encode(array("error" => array("code" => $this->error_code)));
    }
}