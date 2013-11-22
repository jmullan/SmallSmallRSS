<?php
namespace SmallSmallRSS\Handlers;
interface IHandler
{
    function CRSFIgnore($method);
    function before($method);
    function after();
}
