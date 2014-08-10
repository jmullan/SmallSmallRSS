<?php
namespace SmallSmallRSS\Handlers;

interface IHandler
{
    public function ignoreCSRF($method);
    public function before($method);
    public function after();
}
