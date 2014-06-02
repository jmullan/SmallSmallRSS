<?php
namespace SmallSmallRSS;

class FeedItem_Atom extends FeedItem_Abstract
{
    public function getId()
    {
        $id = $this->elem->getElementsByTagName('id')->item(0);
        if ($id) {
            return $id->nodeValue;
        } else {
            return $this->getLink();
        }
    }

    public function getDate()
    {
        $updated = $this->elem->getElementsByTagName('updated')->item(0);
        if ($updated) {
            return strtotime($updated->nodeValue);
        }
        $published = $this->elem->getElementsByTagName('published')->item(0);
        if ($published) {
            return strtotime($published->nodeValue);
        }
        $date = $this->xpath->query('dc:date', $this->elem)->item(0);
        if ($date) {
            return strtotime($date->nodeValue);
        }
    }

    public function getLink()
    {
        $links = $this->elem->getElementsByTagName('link');
        foreach ($links as $link) {
            if ($link
                && $link->hasAttribute('href')
                && (!$link->hasAttribute('rel')
                    || $link->getAttribute('rel') == 'alternate'
                    || $link->getAttribute('rel') == 'standout')) {
                return $link->getAttribute('href');
            }
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
        $content = $this->elem->getElementsByTagName('content')->item(0);
        if ($content) {
            if ($content->hasAttribute('type')) {
                if ($content->getAttribute('type') == 'xhtml') {
                    return $this->doc->saveXML($content->firstChild->nextSibling);
                }
            }
            return $content->nodeValue;
        }
    }

    public function getDescription()
    {
        $content = $this->elem->getElementsByTagName('summary')->item(0);
        if ($content) {
            if ($content->hasAttribute('type')) {
                if ($content->getAttribute('type') == 'xhtml') {
                    return $this->doc->saveXML($content->firstChild->nextSibling);
                }
            }
            return $content->nodeValue;
        }

    }

    public function getCategories()
    {
        $categories = $this->elem->getElementsByTagName('category');
        $cats = array();
        foreach ($categories as $cat) {
            if ($cat->hasAttribute('term')) {
                array_push($cats, $cat->getAttribute('term'));
            }
        }
        $categories = $this->xpath->query('dc:subject', $this->elem);
        foreach ($categories as $cat) {
            array_push($cats, $cat->nodeValue);
        }
        return $cats;
    }

    public function getEnclosures()
    {
        $links = $this->elem->getElementsByTagName('link');
        $encs = array();
        foreach ($links as $link) {
            if ($link && $link->hasAttribute('href') && $link->hasAttribute('rel')) {
                if ($link->getAttribute('rel') == 'enclosure') {
                    $enc = new \SmallSmallRSS\FeedEnclosure();
                    $enc->type = $link->getAttribute('type');
                    $enc->link = $link->getAttribute('href');
                    $enc->length = $link->getAttribute('length');
                    array_push($encs, $enc);
                }
            }
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
                if ($desc) $enc->title = strip_tags($desc->nodeValue);
            }
            $encs[] = $enc;
        }
        return $encs;
    }
}
