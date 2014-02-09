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
        \SmallSmallRSS\Hooks::FILTER_INCOMING_ARTICLE
    );

    public function hookFilterIncomingArticle($article)
    {
        $owner_uid = $article["owner_uid"];
        if (strpos($article["link"], "penny-arcade.com") !== false && strpos($article["title"], "Comic:") !== false) {
            if (strpos($article["plugin_data"], "pennyarcade,$owner_uid:") === false) {
                $doc = new DOMDocument();
                $doc->loadHTML(\SmallSmallRSS\Fetcher::simpleFetch($article["link"]));

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
                $doc = new DOMDocument();
                $doc->loadHTML(\SmallSmallRSS\Fetcher::simpleFetch($article["link"]));

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
