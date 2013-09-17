<?php
class Af_SFBG extends Plugin {

    private $host;

    function about() {
        return array(
            1.1,
            "SF Bay Guardian Feeds are terrible",
            "jmullan"
        );
    }

    function init($host) {
        $this->host = $host;
        $host->add_hook($host::HOOK_GUID_FILTER, $this);
        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
    }

    private static function cleanLink($link) {
        $parts = parse_url($link);
        if (!isset($parts['path']) || $parts['path'] == '') {
            $parts['path'] = '/';
        }
        return 'http://www.sfbg.com' . $parts['path'];
    }

    function hook_guid_filter($item, $guid) {
        $link = $item->get_link();
        if (FALSE === strpos($link, 'sfbg.com')) {
            return $guid;
        } else {
            return self::cleanlink($link);
        }
    }

    function hook_article_filter($article) {
        $link = $article['link'];
        if (FALSE === strpos($link, 'sfbg.com')) {
            return $article;
        }
        $article['link'] = self::cleanlink($link);
        return $article;
    }

    function api_version() {
        return 2;
    }
}
