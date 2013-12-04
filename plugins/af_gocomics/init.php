<?php
class Af_GoComics extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'GoComics Cleanup';
    const DESCRIPTION = 'Strip unnecessary stuff from gocomics feeds';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::FILTER_INCOMING_ARTICLE
    );

    public function hookFilterIncomingArticle($article)
    {
        $owner_uid = $article["owner_uid"];
        if (strpos($article["guid"], "gocomics.com") !== false) {
            if (strpos($article["plugin_data"], "gocomics,$owner_uid:") === false) {
                $doc = new DOMDocument();
                @$doc->loadHTML(\SmallSmallRSS\Fetcher::fetch($article["link"]));

                $basenode = false;

                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//img[@src])'); // we might also check for img[@class='strip'] I guess...

                    $matches = array();
                    $regex = "/(http:\/\/assets.amuniversal.com\/.*)/i";
                    foreach ($entries as $entry) {
                        if (preg_match($regex, $entry->getAttribute("src"), $matches)) {
                            $entry->setAttribute("src", $matches[0]);
                            $basenode = $entry;
                            break;
                        }
                    }

                    if ($basenode) {
                        $article["content"] = $doc->saveXML($basenode);
                        $article["plugin_data"] = "gocomics,$owner_uid:" . $article["plugin_data"];
                    }
                }
            } elseif (isset($article["stored"]["content"])) {
                $article["content"] = $article["stored"]["content"];
            }
        }
        return $article;
    }
}
