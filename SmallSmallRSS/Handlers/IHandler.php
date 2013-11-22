<?php
namespace SmallSmallRSS\Handlers;
interface IHandler
{
    function ignoreCSRF($method);
    function before($method);
    function after();
}
