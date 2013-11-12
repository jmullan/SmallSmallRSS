<?php
namespace SmallSmallRSS\Renderers;

abstract class Base
{
    function render()
    {
    }
    function render_php($absolute_path)
    {
        require_once $absolute_path;
    }
}
