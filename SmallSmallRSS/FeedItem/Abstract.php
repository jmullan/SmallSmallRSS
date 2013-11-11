<?php
abstract class FeedItem_Abstract extends FeedItem {
    protected $elem;
    protected $xpath;
    protected $doc;

    function __construct($elem, $doc, $xpath) {
        $this->elem = $elem;
        $this->xpath = $xpath;
        $this->doc = $doc;
    }

    abstract function get_id();
    abstract function get_date();
    abstract function get_link();
    abstract function get_title();
    abstract function get_description();
    abstract function get_content();
    abstract function get_comments_url();
    abstract function get_categories();
    abstract function get_enclosures();

    function get_author() {
        $author = $this->elem->getElementsByTagName("author")->item(0);
        if ($author) {
            $name = $author->getElementsByTagName("name")->item(0);
            if ($name) {
                return $name->nodeValue;
            }
            $email = $author->getElementsByTagName("email")->item(0);
            if ($email) {
                return $email->nodeValue;
            }
            if ($author->nodeValue) {
                return $author->nodeValue;
            }
        }
        $author = $this->xpath->query("dc:creator", $this->elem)->item(0);
        if ($author) {
            return $author->nodeValue;
        }
    }
    function get_comments_count() {
        $comments = $this->xpath->query("slash:comments", $this->elem)->item(0);
        if ($comments) {
            return $comments->nodeValue;
        }
    }
}
