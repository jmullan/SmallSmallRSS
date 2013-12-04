<?php

class Af_Explosm extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Cyanide and Happiness Cleanup';
    const DESCRIPTION = 'Strip unnecessary stuff from Cyanide and Happiness feeds';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::FILTER_INCOMING_ARTICLE
    );

    public function hookFilterIncomingArticle($article)
    {
        $owner_uid = $article["owner_uid"];
        if (strpos($article["link"], "explosm.net/comics") !== false) {
            if (strpos($article["plugin_data"], "explosm,$owner_uid:") === false) {
                $doc = new DOMDocument();
                @$doc->loadHTML(\SmallSmallRSS\Fetcher::fetch($article["link"]));
                $basenode = false;
                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...
                    $matches = array();
                    $regex = "/(http:\/\/.*\/db\/files\/Comics\/.*)/i";
                    foreach ($entries as $entry) {
                        if (preg_match($regex, $entry->getAttribute("src"), $matches)) {
                            $basenode = $entry;
                            break;
                        }
                    }
                    if ($basenode) {
                        $article["content"] = $doc->saveXML($basenode);
                        $article["plugin_data"] = "explosm,$owner_uid:" . $article["plugin_data"];
                    }
                }
            } elseif (isset($article["stored"]["content"])) {
                $article["content"] = $article["stored"]["content"];
            }
        }
        return $article;
    }
}
