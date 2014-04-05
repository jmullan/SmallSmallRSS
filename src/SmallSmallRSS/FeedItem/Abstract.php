<?php
namespace SmallSmallRSS;

abstract class FeedItem_Abstract
{
    protected $elem;
    protected $xpath;
    protected $doc;

    public function __construct($elem, $doc, $xpath)
    {
        $this->elem = $elem;
        $this->xpath = $xpath;
        $this->doc = $doc;
    }

    abstract public function get_id();
    abstract public function get_date();
    abstract public function getLink();
    abstract public function getTitle();
    abstract public function get_description();
    abstract public function get_content();
    abstract public function get_categories();
    abstract public function get_enclosures();

    public function get_author()
    {
        $author = $this->elem->getElementsByTagName('author')->item(0);
        if ($author) {
            $name = $author->getElementsByTagName('name')->item(0);
            if ($name) {
                return $name->nodeValue;
            }
            $email = $author->getElementsByTagName('email')->item(0);
            if ($email) {
                return $email->nodeValue;
            }
            if ($author->nodeValue) {
                return $author->nodeValue;
            }
        }
        $author = $this->xpath->query('dc:creator', $this->elem)->item(0);
        if ($author) {
            return $author->nodeValue;
        }
    }
    public function get_comments_count()
    {
        $comments = $this->xpath->query('slash:comments', $this->elem)->item(0);
        if ($comments) {
            return $comments->nodeValue;
        }
    }
    public function get_comments_url()
    {
    }
}
