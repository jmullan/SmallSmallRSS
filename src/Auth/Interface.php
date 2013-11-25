<?php
namespace SmallSmallRSS;

interface Auth_Interface
{
    function authenticate($login, $password);
}
