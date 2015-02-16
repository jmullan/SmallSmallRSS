<?php
namespace SmallSmallRSS;

abstract class FeedItemAbstract
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

    abstract public function getId();
    abstract public function getDate();
    abstract public function getLink();
    abstract public function getTitle();
    abstract public function getDescription();
    abstract public function getContent();
    abstract public function getCategories();
    abstract public function getEnclosures();

    public function getAuthor()
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
    public function getCommentsCount()
    {
        $comments = $this->xpath->query('slash:comments', $this->elem)->item(0);
        if ($comments) {
            return $comments->nodeValue;
        }
    }
    public function getCommentsUrl()
    {
    }
}
