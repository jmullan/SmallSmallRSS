<?php
namespace SmallSmallRSS\Renderers;

class LoginPage extends \SmallSmallRSS\Renderers\Base
{
    public function render()
    {
        header('Cache-Control: public');
        $this->render_php(__DIR__ . '/templates/login_form.php');
        exit;
    }
}
