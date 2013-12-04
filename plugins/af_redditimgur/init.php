<?php

class Af_RedditImgur extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Reddit + Imgur';
    const DESCRIPTION = 'Inline image links in Reddit RSS feeds';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::FILTER_INCOMING_ARTICLE
    );

    public function hookFilterIncomingArticle($article)
    {
        $owner_uid = $article["owner_uid"];
        $force = false;
        if (strpos($article["link"], "reddit.com/r/") !== false) {
            if ($force || strpos($article["plugin_data"], "redditimgur,$owner_uid:") === false) {
                $doc = new DOMDocument();
                @$doc->loadHTML($article["content"]);

                if ($doc) {
                    $xpath = new DOMXPath($doc);
                    $entries = $xpath->query('(//a[@href]|//img[@src])');

                    $found = false;

                    foreach ($entries as $entry) {
                        if ($entry->hasAttribute("href")) {
                            if (preg_match("/\.(jpg|jpeg|gif|png)$/i", $entry->getAttribute("href"))) {
                                $img = $doc->createElement('img');
                                $img->setAttribute("src", $entry->getAttribute("href"));
                                $br = $doc->createElement('br');
                                $entry->parentNode->insertBefore($img, $entry);
                                $entry->parentNode->insertBefore($br, $entry);
                                $found = true;
                            }
                            $regex = "/^http:\/\/imgur.com\/([^\.\/]+$)/";
                            // links to imgur pages
                            $matches = array();
                            if (preg_match($regex, $entry->getAttribute("href"), $matches)) {

                                $token = $matches[1];

                                $album_content = \SmallSmallRSS\Fetcher::fetch(
                                    $entry->getAttribute("href"),
                                    false,
                                    false,
                                    false,
                                    false,
                                    10
                                );

                                if ($album_content && $token) {
                                    $adoc = new DOMDocument();
                                    @$adoc->loadHTML($album_content);

                                    if ($adoc) {
                                        $axpath = new DOMXPath($adoc);
                                        $aentries = $axpath->query('(//img[@src])');
                                        $regex = "/^http:\/\/i.imgur.com\/$token\./";
                                        foreach ($aentries as $aentry) {
                                            if (preg_match($regex, $aentry->getAttribute("src"))) {
                                                $img = $doc->createElement('img');
                                                $img->setAttribute("src", $aentry->getAttribute("src"));

                                                $br = $doc->createElement('br');

                                                $entry->parentNode->insertBefore($img, $entry);
                                                $entry->parentNode->insertBefore($br, $entry);

                                                $found = true;

                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            // linked albums, ffs
                            $regex = "/^http:\/\/imgur.com\/(a|album)\/[^\.]+$/";
                            if (preg_match($regex, $entry->getAttribute("href"), $matches)) {

                                $album_content = \SmallSmallRSS\Fetcher::fetch(
                                    $entry->getAttribute("href"),
                                    false,
                                    false,
                                    false,
                                    false,
                                    10
                                );

                                if ($album_content) {
                                    $adoc = new DOMDocument();
                                    @$adoc->loadHTML($album_content);

                                    if ($adoc) {
                                        $axpath = new DOMXPath($adoc);
                                        $aentries = $axpath->query("//div[@class='image']//a[@href and @class='zoom']");

                                        foreach ($aentries as $aentry) {
                                            $img = $doc->createElement('img');
                                            $img->setAttribute("src", $aentry->getAttribute("href"));
                                            $entry->parentNode->insertBefore($doc->createElement('br'), $entry);

                                            $br = $doc->createElement('br');

                                            $entry->parentNode->insertBefore($img, $entry);
                                            $entry->parentNode->insertBefore($br, $entry);

                                            $found = true;
                                        }
                                    }
                                }
                            }
                        }

                        // remove tiny thumbnails
                        if ($entry->hasAttribute("src")) {
                            if ($entry->parentNode && $entry->parentNode->parentNode) {
                                $entry->parentNode->parentNode->removeChild($entry->parentNode);
                            }
                        }
                    }

                    $node = $doc->getElementsByTagName('body')->item(0);

                    if ($node && $found) {
                        $article["content"] = $doc->saveXML($node);
                        if (!$force) {
                            $article["plugin_data"] = "redditimgur,$owner_uid:" . $article["plugin_data"];
                        }
                    }
                }
            } elseif (isset($article["stored"]["content"])) {
                $article["content"] = $article["stored"]["content"];
            }
        }
        return $article;
    }
}
