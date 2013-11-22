<?php

class Af_SFBG extends \SmallSmallRSS\Plugin {

    private $host;

    const API_VERSION = 2;
    const VERSION = 1.1;
    const NAME = 'SF Bay Guardian Fixer';
    const DESCRIPTION = 'Fixes urls and guids of SF Bay Guardian Feeds';
    const AUTHOR = 'jmullan';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\PluginHost::HOOK_GUID_FILTER,
        \SmallSmallRSS\PluginHost::HOOK_ARTICLE_FILTER
    );

    private static function should_run_against($link)
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

    public function hook_guid_filter($item, $guid)
    {
        $link = $item->get_link();
        if (self::should_run_against($link)) {
            $guid = self::cleanlink($link);
        }
        return $guid;
    }

    public function hookArticleFilter($article)
    {
        $link = $article['link'];
        if (self::should_run_against($link)) {
            $article['link'] = self::cleanlink($link);
        }
        return $article;
    }
}
