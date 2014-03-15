<?php

class Af_Buni extends \SmallSmallRSS\Plugin
{
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'BuniComic Cleanup';
    const DESCRIPTION = 'Fix Buni rss feed';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::FILTER_INCOMING_ARTICLE
    );

    public function hookFilterIncomingArticle($article)
    {
        $owner_uid = $article["owner_uid"];
        if (strpos($article["guid"], "bunicomic.com") !== false) {
            if (strpos($article["plugin_data"], "buni,$owner_uid:") === false) {
                $doc = new DOMDocument();
                @$doc->loadHTML(\SmallSmallRSS\Fetcher::simpleFetch($article["link"]));
                $basenode = false;
                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//img[@src])');
                    $matches = array();
                    $regex = "/(http:\/\/www.bunicomic.com\/comics\/\d{4}.*)/i";
                    foreach ($entries as $entry) {
                        if (preg_match($regex, $entry->getAttribute("src"), $matches)) {
                            $basenode = $entry;
                            break;
                        }
                    }
                    if ($basenode) {
                        $article["content"] = $doc->saveXML($basenode);
                        $article["plugin_data"] = "buni,$owner_uid:" . $article["plugin_data"];
                    }
                }
            } elseif (isset($article["stored"]["content"])) {
                $article["content"] = $article["stored"]["content"];
            }
        }
        return $article;
    }
}
