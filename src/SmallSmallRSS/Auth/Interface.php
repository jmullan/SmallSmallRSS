<?php
namespace SmallSmallRSS;

interface Auth_Interface
{
    public function authenticate($login, $password);
}
