<?php
namespace SmallSmallRSS\Handlers;
class ProtectedHandler extends Handler
{
    public function before($method)
    {
        return parent::before($method) && $_SESSION['uid'];
    }
}
