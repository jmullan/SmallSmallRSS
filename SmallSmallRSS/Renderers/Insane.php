<?php
namespace SmallSmallRSS\Renderers;

class Insance extends \SmallSmallRSS\Renderers\Base
{
    public function __construct($errors)
    {
        $this->errors = $errors;
    }
    public function render()
    {
        $params = array('errors' => $this->errors);
        header('Cache-Control: public');
        $this->renderPHP(__DIR__ . '/templates/insane.php', $params);
        exit;
    }
}
