<?php

class Af_Buttersafe extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Buttersafe Cleanup';
    const DESCRIPTION = 'Strip unnecessary stuff from Buttersafe feeds';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\PluginHost::HOOK_ARTICLE_FILTER
    );

    public function hookArticleFilter($article)
    {
        $owner_uid = $article["owner_uid"];
        if (strpos($article["guid"], "buttersafe.com") !== false) {
            if (strpos($article["plugin_data"], "buttersafe,$owner_uid:") === false) {
                $doc = new DOMDocument();
                @$doc->loadHTML(\SmallSmallRSS\Fetcher::fetch($article["link"]));
                $basenode = false;
                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//img[@src])');
                    $matches = array();
                    $regex = "/(http:\/\/buttersafe.com\/comics\/\d{4}.*)/i";
                    foreach ($entries as $entry) {
                        if (preg_match($regex, $entry->getAttribute("src"), $matches)) {
                            $basenode = $entry;
                            break;
                        }
                    }
                    if ($basenode) {
                        $article["content"] = $doc->saveXML($basenode);
                        $article["plugin_data"] = "buttersafe,$owner_uid:" . $article["plugin_data"];
                    }
                }
            } elseif (isset($article["stored"]["content"])) {
                $article["content"] = $article["stored"]["content"];
            }
        }
        return $article;
    }
}
