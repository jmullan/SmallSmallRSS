<?php
namespace SmallSmallRSS\Renderers;

class Insane extends \SmallSmallRSS\Renderers\Base
{
    public function __construct($errors)
    {
        $this->errors = $errors;
    }
    public function render()
    {
        header('Cache-Control: public');
        $params = array('errors' => $this->errors);
        $this->renderPHP(__DIR__ . '/templates/insane.php', $params);
        exit;
    }
}
