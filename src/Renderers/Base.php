<?php
namespace SmallSmallRSS\Renderers;

abstract class Base
{
    public function render()
    {
    }
    public function renderPHP($absolute_path, $template_params = array())
    {
        if ($template_params) {
            # never do this, am I right? Sigh.
            extract($template_params);
        }
        require_once $absolute_path;
    }
}
