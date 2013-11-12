<?php
namespace SmallSmallRSS\Handlers;
interface IHandler
{
    function csrf_ignore($method);
    function before($method);
    function after();
}
