<?php
namespace SmallSmallRSS;

class FeedItemRSS extends FeedItemAbstract
{
    public function getId()
    {
        $id = $this->elem->getElementsByTagName('guid')->item(0);
        if ($id) {
            return $id->nodeValue;
        } else {
            return $this->getLink();
        }
    }

    public function getDate()
    {
        $pubDate = $this->elem->getElementsByTagName('pubDate')->item(0);
        if ($pubDate) {
            return strtotime($pubDate->nodeValue);
        }
        $date = $this->xpath->query('dc:date', $this->elem)->item(0);
        if ($date) {
            return strtotime($date->nodeValue);
        }
    }

    public function getLink()
    {
        $links = $this->xpath->query('atom:link', $this->elem);
        foreach ($links as $link) {
            if ($link && $link->hasAttribute('href')
                && (!$link->hasAttribute('rel')
                    || $link->getAttribute('rel') == 'alternate'
                    || $link->getAttribute('rel') == 'standout')) {
                return $link->getAttribute('href');
            }
        }
        $link = $this->elem->getElementsByTagName('guid')->item(0);
        if ($link && $link->hasAttributes() && $link->getAttribute('isPermaLink') == 'true') {
            return $link->nodeValue;
        }
        $link = $this->elem->getElementsByTagName('link')->item(0);
        if ($link) {
            return $link->nodeValue;
        }
    }

    public function getTitle()
    {
        $title = $this->elem->getElementsByTagName('title')->item(0);
        if ($title) {
            return $title->nodeValue;
        }
    }

    public function getContent()
    {
        $content = $this->xpath->query('content:encoded', $this->elem)->item(0);
        if ($content) {
            return $content->nodeValue;
        }
        $content = $this->elem->getElementsByTagName('description')->item(0);
        if ($content) {
            return $content->nodeValue;
        }
    }

    public function getDescription()
    {
        $summary = $this->elem->getElementsByTagName('description')->item(0);
        if ($summary) {
            return $summary->nodeValue;
        }
    }

    public function getCategories()
    {
        $categories = $this->elem->getElementsByTagName('category');
        $cats = array();
        foreach ($categories as $cat) {
            array_push($cats, $cat->nodeValue);
        }
        $categories = $this->xpath->query('dc:subject', $this->elem);
        foreach ($categories as $cat) {
            array_push($cats, $cat->nodeValue);
        }
        return $cats;
    }

    public function getEnclosures()
    {
        $enclosures = $this->elem->getElementsByTagName('enclosure');
        $encs = array();
        foreach ($enclosures as $enclosure) {
            $enc = new \SmallSmallRSS\FeedEnclosure();
            $enc->type = $enclosure->getAttribute('type');
            $enc->link = $enclosure->getAttribute('url');
            $enc->length = $enclosure->getAttribute('length');
            $encs[] = $enc;
        }
        $enclosures = $this->xpath->query('media:content', $this->elem);
        foreach ($enclosures as $enclosure) {
            $enc = new \SmallSmallRSS\FeedEnclosure();
            $enc->type = $enclosure->getAttribute('type');
            $enc->link = $enclosure->getAttribute('url');
            $enc->length = $enclosure->getAttribute('length');
            $encs[] = $enc;
        }
        $enclosures = $this->xpath->query("media:group", $this->elem);
        foreach ($enclosures as $enclosure) {
            $enc = new FeedEnclosure();
            $content = $this->xpath->query("media:content", $enclosure)->item(0);
            $enc->type = $content->getAttribute("type");
            $enc->link = $content->getAttribute("url");
            $enc->length = $content->getAttribute("length");
            $desc = $this->xpath->query("media:description", $content)->item(0);
            if ($desc) {
                $enc->title = strip_tags($desc->nodeValue);
            } else {
                $desc = $this->xpath->query("media:description", $enclosure)->item(0);
                if ($desc) {
                    $enc->title = strip_tags($desc->nodeValue);
                }
            }
            $encs[] = $enc;
        }
        return $encs;
    }
}
