<?php

class Af_Whomp extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Whomp! Images';
    const DESCRIPTION = 'Embed images in Whomp!';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::FILTER_INCOMING_ARTICLE
    );

    public function hookFilterIncomingArticle($article)
    {
        $owner_uid = $article["owner_uid"];
        if (strpos($article["guid"], "whompcomic.com") !== false) {
            if (strpos($article["plugin_data"], "whomp,$owner_uid:") === false) {
                $doc = new DOMDocument();
                @$doc->loadHTML(\SmallSmallRSS\Fetcher::fetch($article["link"]));
                $basenode = false;
                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//img[@src])');
                    $matches = array();
                    $regex = "/(http:\/\/www\.whompcomic\.com\/comics\/.*)/i";
                    foreach ($entries as $entry) {
                        $src = $entry->getAttribute("src");
                        if (preg_match($regex, $src, $matches)) {
                            $entry->setAttribute("src", $matches[0]);
                            $basenode = $entry;
                            break;
                        }
                    }
                    if ($basenode) {
                        $article["content"] = $doc->saveXML($basenode);
                        $article["plugin_data"] = "whomp,$owner_uid:" . $article["plugin_data"];
                    }
                }
            } elseif (isset($article["stored"]["content"])) {
                $article["content"] = $article["stored"]["content"];
            }
        }
        return $article;
    }
}
