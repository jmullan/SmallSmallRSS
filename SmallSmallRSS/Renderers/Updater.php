<?php
namespace SmallSmallRSS\Renderers;

class Updater extends \SmallSmallRSS\Renderers\Base
{
    public function render()
    {
        header('Cache-Control: public');
        $this->renderPHP(__DIR__ . '/templates/updater.php');
        exit;
    }
}
