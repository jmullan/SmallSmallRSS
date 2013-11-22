<?php
namespace SmallSmallRSS\Handlers;
class Handler implements IHandler
{
    protected $dbh;
    protected $args;

    function __construct($args)
    {
        $this->args = $args;
    }

    function CRSFIgnore($method)
    {
        return true;
    }

    function before($method)
    {
        return true;
    }

    function after()
    {
        return true;
    }
}
