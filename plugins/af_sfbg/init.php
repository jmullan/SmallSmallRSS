<?php

class Af_SFBG extends \SmallSmallRSS\Plugin
{

    private $host;

    const API_VERSION = 2;
    const VERSION = 1.1;
    const NAME = 'SF Bay Guardian Fixer';
    const DESCRIPTION = 'Fixes urls and guids of SF Bay Guardian Feeds';
    const AUTHOR = 'jmullan';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::GUID_FILTER,
        \SmallSmallRSS\Hooks::ARTICLE_FILTER
    );

    private static function shouldRunAgainst($link)
    {
        return (
            false !== strpos($link, 'sfbg.com')
            || false !== strpos($link, 'sfbayguardian.com')
        );
    }

    private static function cleanLink($link)
    {
        $parts = parse_url($link);
        if (!isset($parts['path']) || $parts['path'] == '') {
            $parts['path'] = '/';
        }
        return 'http://www.sfbg.com' . $parts['path'];
    }

    public function hookGuidFilter($args)
    {
        $link = $args['item']->get_link();
        $guid = $args['guid'];
        if (self::shouldRunAgainst($link)) {
            $guid = self::cleanlink($link);
        }
        return $guid;
    }

    public function hookArticleFilter($article)
    {
        $link = $article['link'];
        if (self::shouldRunAgainst($link)) {
            $article['link'] = self::cleanlink($link);
        }
        return $article;
    }
}
