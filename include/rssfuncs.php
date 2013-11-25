<?php

function cache_images($html, $site_url, $debug)
{
    $cache_dir = \SmallSmallRSS\Config::get('CACHE_DIR') . "/images";
    libxml_use_internal_errors(true);
    $charset_hack = '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head>';
    $doc = new DOMDocument();
    $doc->loadHTML($charset_hack . $html);
    $xpath = new DOMXPath($doc);
    $entries = $xpath->query('(//img[@src])');
    foreach ($entries as $entry) {
        if ($entry->hasAttribute('src')) {
            $src = rewrite_relative_url($site_url, $entry->getAttribute('src'));
            $local_filename = \SmallSmallRSS\Config::get('CACHE_DIR') . "/images/" . sha1($src) . ".png";
            if (!file_exists($local_filename)) {
                $file_content = \SmallSmallRSS\Fetcher::fetch($src);
                if ($file_content && strlen($file_content) > 1024) {
                    file_put_contents($local_filename, $file_content);
                }
            }
            if (file_exists($local_filename)) {
                $entry->setAttribute(
                    'src',
                    \SmallSmallRSS\Config::get('SELF_URL_PATH') . '/image.php?url=' . base64_encode($src)
                );
            }
        }
    }
    $node = $doc->getElementsByTagName('body')->item(0);
    return $doc->saveXML($node);
}


function get_article_filters($filters, $title, $content, $link, $timestamp, $author, $tags)
{
    $matches = array();

    foreach ($filters as $filter) {
        $match_any_rule = $filter["match_any_rule"];
        $inverse = $filter["inverse"];
        $filter_match = false;

        foreach ($filter["rules"] as $rule) {
            $match = false;
            $reg_exp = str_replace('/', '\/', $rule["reg_exp"]);
            $rule_inverse = $rule["inverse"];

            if (!$reg_exp) {
                continue;
            }

            switch ($rule["type"]) {
                case "title":
                    $match = @preg_match("/$reg_exp/i", $title);
                    break;
                case "content":
                    // we don't need to deal with multiline regexps
                    $content = preg_replace("/[\r\n\t]/", "", $content);

                    $match = @preg_match("/$reg_exp/i", $content);
                    break;
                case "both":
                    // we don't need to deal with multiline regexps
                    $content = preg_replace("/[\r\n\t]/", "", $content);

                    $match = (@preg_match("/$reg_exp/i", $title) || @preg_match("/$reg_exp/i", $content));
                    break;
                case "link":
                    $match = @preg_match("/$reg_exp/i", $link);
                    break;
                case "author":
                    $match = @preg_match("/$reg_exp/i", $author);
                    break;
                case "tag":
                    foreach ($tags as $tag) {
                        if (@preg_match("/$reg_exp/i", $tag)) {
                            $match = true;
                            break;
                        }
                    }
                    break;
            }

            if ($rule_inverse) {
                $match = !$match;
            }

            if ($match_any_rule) {
                if ($match) {
                    $filter_match = true;
                    break;
                }
            } else {
                $filter_match = $match;
                if (!$match) {
                    break;
                }
            }
        }

        if ($inverse) {
            $filter_match = !$filter_match;
        }

        if ($filter_match) {
            foreach ($filter["actions"] as $action) {
                array_push($matches, $action);
                // if Stop action encountered, perform no further processing
                if ($action["type"] == "stop") {
                    return $matches;
                }
            }
        }
    }
    return $matches;
}

function find_article_filter($filters, $filter_name)
{
    foreach ($filters as $f) {
        if ($f["type"] == $filter_name) {
            return $f;
        };
    }
    return false;
}

function find_article_filters($filters, $filter_name)
{
    $results = array();
    foreach ($filters as $f) {
        if ($f["type"] == $filter_name) {
            array_push($results, $f);
        };
    }
    return $results;
}

function calculate_article_score($filters)
{
    $score = 0;
    foreach ($filters as $f) {
        if ($f["type"] == "score") {
            $score += $f["param"];
        };
    }
    return $score;
}

function labels_contains_caption($labels, $caption)
{
    foreach ($labels as $label) {
        if ($label[1] == $caption) {
            return true;
        }
    }
    return false;
}

function assign_article_to_label_filters($id, $filters, $owner_uid, $article_labels)
{
    foreach ($filters as $f) {
        if ($f["type"] == "label") {
            if (!labels_contains_caption($article_labels, $f["param"])) {
                \SmallSmallRSS\Labels::addArticle($id, $f["param"], $owner_uid);
            }
        }
    }
}

function make_guid_from_title($title)
{
    return preg_replace(
        "/[ \"\',.:;]/",
        "-",
        mb_strtolower(strip_tags($title), 'utf-8')
    );
}
