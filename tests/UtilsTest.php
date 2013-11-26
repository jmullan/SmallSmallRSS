<?php
namespace tests;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testThings()
    {
        $utils = new \SmallSmallRSS\Utils();
        $this->assertEquals(
            'http://example.com/foo',
            $utils->rewriteRelativeUrl('http://example.com/', 'foo')
        );
        $this->assertEquals(
            'http://example.com/foo',
            $utils->rewriteRelativeUrl('http://example.com', 'foo')
        );
        $this->assertEquals(
            'http://example.com/foo',
            $utils->rewriteRelativeUrl('http://example.com/bar', '/foo')
        );
        $this->assertEquals(
            'http://example.com/foo',
            $utils->rewriteRelativeUrl('http://example.com/bar/', '/foo')
        );
        $this->assertEquals(
            'http://example.com/foo',
            $utils->rewriteRelativeUrl('http://example.com/bar/baz', '/foo')
        );
        $this->assertEquals(
            'http://example.com/bar/foo',
            $utils->rewriteRelativeUrl('http://example.com/bar/baz', 'foo')
        );
        $this->assertEquals(
            'http://example.com/bar/baz/foo',
            $utils->rewriteRelativeUrl('http://example.com/bar/baz/', 'foo')
        );
        $this->assertEquals(
            'http://test.example.com/foo',
            $utils->rewriteRelativeUrl('http://example.com/', '//test.example.com/foo')
        );
    }
}
