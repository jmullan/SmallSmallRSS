<?php
class Af_PennyArcade extends \SmallSmallRSS\Plugin
{

    private $host;

    const API_VERSION = 2;
    const VERSION = 1.1;
    const NAME = 'Penny Arcade Cleanup';
    const DESCRIPTION = 'Strip unnecessary stuff from PA feeds';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\PluginHost::HOOK_ARTICLE_FILTER
    );

    function hookArticleFilter($article)
    {
        $owner_uid = $article["owner_uid"];
        if (strpos($article["link"], "penny-arcade.com") !== false && strpos($article["title"], "Comic:") !== false) {
            if (strpos($article["plugin_data"], "pennyarcade,$owner_uid:") === false) {

                if ($debug_enabled) {
                    _debug("af_pennyarcade: Processing comic");
                }

                $doc = new DOMDocument();
                $doc->loadHTML(\SmallSmallRSS\Fetcher::fetch($article["link"]));

                $basenode = false;

                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//div[@class="post comic"])');

                    foreach ($entries as $entry) {
                        $basenode = $entry;
                    }

                    if ($basenode) {
                        $article["content"] = $doc->saveXML($basenode);
                        $article["plugin_data"] = "pennyarcade,$owner_uid:" . $article["plugin_data"];
                    }
                }
            } elseif (isset($article["stored"]["content"])) {
                $article["content"] = $article["stored"]["content"];
            }
        }

        if (strpos($article["link"], "penny-arcade.com") !== false
            && strpos($article["title"], "News Post:") !== false) {

            if (strpos($article["plugin_data"], "pennyarcade,$owner_uid:") === false) {
                if ($debug_enabled) {
                    _debug("af_pennyarcade: Processing news post");
                }
                $doc = new DOMDocument();
                $doc->loadHTML(\SmallSmallRSS\Fetcher::fetch($article["link"]));

                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//div[@class="post"])');

                    $basenode = false;

                    foreach ($entries as $entry) {
                        $basenode = $entry;
                    }

                    $uninteresting = $xpath->query('(//div[@class="heading"])');
                    foreach ($uninteresting as $i) {
                        $i->parentNode->removeChild($i);
                    }

                    if ($basenode) {
                        $article["content"] = $doc->saveXML($basenode);
                        $article["plugin_data"] = "pennyarcade,$owner_uid:" . $article["plugin_data"];
                    }
                }
            } elseif (isset($article["stored"]["content"])) {
                $article["content"] = $article["stored"]["content"];
            }
        }

        return $article;
    }
}
