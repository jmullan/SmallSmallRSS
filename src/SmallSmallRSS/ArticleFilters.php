<?php
namespace SmallSmallRSS;

class ArticleFilters
{
    public function get($filters, $title, $content, $link, $timestamp, $author, $tags)
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

    public function matchArticle($filters, $filter_name)
    {
        foreach ($filters as $f) {
            if ($f["type"] == $filter_name) {
                return $f;
            };
        }
        return false;
    }

    public function calculateScore($filters)
    {
        $score = 0;
        foreach ($filters as $f) {
            if ($f["type"] == "score") {
                $score += $f["param"];
            };
        }
        return $score;
    }

    public function assignArticleToLabel($id, $filters, $owner_uid, $article_labels)
    {
        foreach ($filters as $f) {
            if ($f["type"] == "label") {
                if (!\SmallSmallRSS\Labels::ContainsCaption($article_labels, $f["param"])) {
                    \SmallSmallRSS\Labels::addArticle($id, $f["param"], $owner_uid);
                }
            }
        }
    }
}
