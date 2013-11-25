<?php
namespace SmallSmallRSS\Handlers;
class ProtectedHandler extends Handler
{
    function before($method)
    {
        return parent::before($method) && $_SESSION['uid'];
    }
}
