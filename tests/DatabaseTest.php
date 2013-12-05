<?php
namespace tests;

class DatabaseTest extends \PHPUnit_Framework_TestCase
{
    public function testDatabase()
    {
        $this->assertEquals("'foo'", \SmallSmallRSS\Database::quote('foo'));
        $this->assertEquals("''foo''", \SmallSmallRSS\Database::quote("'foo'"));
    }
    public function testToSQLBool()
    {
        $this->assertEquals('true', \SmallSmallRSS\Database::toSQLBool(true));
        $this->assertEquals('true', \SmallSmallRSS\Database::toSQLBool(1));
        $this->assertEquals('true', \SmallSmallRSS\Database::toSQLBool('1'));

        $this->assertEquals('false', \SmallSmallRSS\Database::toSQLBool(false));
        $this->assertEquals('false', \SmallSmallRSS\Database::toSQLBool(0));
        $this->assertEquals('false', \SmallSmallRSS\Database::toSQLBool('0'));
        $this->assertEquals('false', \SmallSmallRSS\Database::toSQLBool(null));
        $this->assertEquals('false', \SmallSmallRSS\Database::toSQLBool(array()));
        $this->assertEquals('false', \SmallSmallRSS\Database::toSQLBool(''));
    }
    public function fromSQLBool()
    {
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('t'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('true'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('y'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('yes'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('T'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('True'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('Y'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('yES'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('1'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool('01'));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool(1));
        $this->assertTrue(\SmallSmallRSS\Database::fromSQLBool(true));

        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool('f'));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool('F'));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool('False'));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool('FALSE'));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool('false'));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool('No'));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool('N'));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool('0'));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool(0));
        $this->assertFalse(\SmallSmallRSS\Database::fromSQLBool(false));
    }
}
