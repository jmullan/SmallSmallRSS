<?php
namespace SmallSmallRSS;

interface AuthInterface
{
    public function authenticate($login, $password);
}
