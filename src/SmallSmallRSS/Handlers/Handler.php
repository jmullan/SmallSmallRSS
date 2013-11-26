<?php
namespace SmallSmallRSS\Handlers;
class Handler implements IHandler
{
    protected $dbh;
    protected $args;

    public function __construct($args)
    {
        $this->args = $args;
    }

    public function ignoreCSRF($method)
    {
        return true;
    }

    public function before($method)
    {
        return true;
    }

    public function after()
    {
        return true;
    }
}
