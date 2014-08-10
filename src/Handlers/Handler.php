<?php
namespace SmallSmallRSS\Handlers;

class Handler implements IHandler
{
    protected $dbh;
    protected $args;

    public function __construct($args)
    {
        $this->args = $args;
    }

    public function ignoreCSRF($method)
    {
        return true;
    }

    public function before($method)
    {
        return true;
    }

    public function after()
    {
        return true;
    }

    public static function getBooleanFromRequest($key)
    {
        $value = false;
        if (isset($_REQUEST[$key])) {
            $value = $_REQUEST[$key];
        }
        return self::checkboxToBool($value);
    }

    public static function checkboxToBool($value)
    {
        if ($value === true || $value === 1) {
            return true;
        } elseif ($value === false || $value === 0) {
            return false;
        }
        if (is_string($value)) {
            $value = strtolower($value);
            if ($value == 't' || $value == 'true' || $value == 'y' || $value == 'yes' || 'value' == 'on') {
                return true;
            } else {
                return false;
            }
        }
        return $value;
    }

    public static function getStringFromRequest($key)
    {
        $value = '';
        if (isset($_REQUEST[$key])) {
            return (string) $_REQUEST[$key];
        } else {
            return '';
        }
    }

    public static function getSQLEscapedStringFromRequest($key)
    {
        return \SmallSmallRSS\Database::escape_string(self::getStringFromRequest($key));
    }

    public static function formatArticle($id, $mark_as_read, $zoom_mode, $owner_uid)
    {
        ob_start();
        $rv = array('id' => $id);
        $feed_id = \SmallSmallRSS\UserEntries::getArticleFeed($id, $owner_uid);
        $rv['feed_id'] = $feed_id;
        if ($mark_as_read) {
            \SmallSmallRSS\UserEntries::markIdsRead(array($id), $owner_uid);
            \SmallSmallRSS\CountersCache::update($feed_id, $owner_uid);
        }
        $substring_for_date = \SmallSmallRSS\Database::getSubstringForDateFunction();
        $result = \SmallSmallRSS\Database::query(
            'SELECT
                 id,
                 title,
                 link,
                 content,
                 feed_id,
                 comments,
                 int_id,
                 ' . $substring_for_date . "(updated,1,16) as updated,
                 (SELECT site_url FROM ttrss_feeds WHERE id = feed_id) as site_url,
                 (SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) as hide_images,
                 (
                      SELECT always_display_enclosures
                      FROM ttrss_feeds
                      WHERE id = feed_id
                 ) as always_display_enclosures,
                 num_comments,
                 tag_cache,
                 author,
                 orig_feed_id,
                 note,
                 cached_content
             FROM ttrss_entries,ttrss_user_entries
             WHERE
                 id = '$id'
                 AND ref_id = id
                 AND owner_uid = $owner_uid"
        );

        if ($result) {
            $line = \SmallSmallRSS\Database::fetch_assoc($result);
            $tag_cache = $line['tag_cache'];
            $line['tags'] = get_article_tags($id, $owner_uid, $line['tag_cache']);
            unset($line['tag_cache']);
            $line['content'] = sanitize(
                $line['content'],
                $owner_uid,
                \SmallSmallRSS\Database::fromSQLBool($line['hide_images']),
                $line['site_url']
            );
            $hooks = \SmallSmallRSS\PluginHost::getInstance()->getHooks(
                \SmallSmallRSS\Hooks::FILTER_INCOMING_ARTICLE
            );
            foreach ($hooks as $p) {
                $line = $p->hookFilterIncomingArticle($line);
            }
            $num_comments = $line['num_comments'];
            $entry_comments = '';
            if ($num_comments > 0) {
                if ($line['comments']) {
                    $comments_url = htmlspecialchars($line['comments']);
                } else {
                    $comments_url = htmlspecialchars($line['link']);
                }
                $entry_comments = "<a target='_blank' href=\"$comments_url\">$num_comments comments</a>";
            } else {
                if ($line['comments'] && $line['link'] != $line['comments']) {
                    $entry_comments = "<a target='_blank' href=\"".htmlspecialchars($line['comments']).'">comments</a>';
                }
            }
            if ($zoom_mode) {
                header('Content-Type: text/html');
                echo '<html><head>';
                echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
                echo '<title>';
                echo \SmallSmallRSS\Config::get('SOFTWARE_NAME');
                echo ' : ' . $line['title'] . '</title>';
                echo '<link rel="stylesheet" type="text/css" href="css/tt-rss.css" />';
                echo '</head><body id="ttrssZoom">';
            }
            echo "<div class=\"postReply\" id=\"POST-$id\">";
            echo "<div class=\"postHeader\" id=\"POSTHDR-$id\">";
            $entry_author = $line['author'];
            if ($entry_author) {
                $entry_author = __(' - ') . $entry_author;
            }
            $parsed_updated = \SmallSmallRSS\Utils::makeLocalDatetime(
                $line['updated'],
                true,
                $owner_uid,
                true
            );
            echo "<div class=\"postDate\">$parsed_updated</div>";
            if ($line['link']) {
                echo "<div class='postTitle'>";
                echo "<a target='_blank' title=\"";
                echo htmlspecialchars($line['title']) . '"';
                echo ' href="' . htmlspecialchars($line['link']) . '">';
                echo $line['title'] . '</a>';
                echo "<span class='author'>$entry_author</span></div>";
            } else {
                echo "<div class='postTitle'>" . $line['title'] . "$entry_author</div>";
            }

            $tags_str = format_tags_string($line['tags'], $id);
            $tags_str_full = join(', ', $line['tags']);
            if (!$tags_str_full) {
                $tags_str_full = __('no tags');
            }
            if (!$entry_comments) {
                $entry_comments = '&nbsp;'; # placeholder
            }

            echo "<div class='postTags' style='float: right'>";
            echo "<img src='images/tag.png' class='tagsPic' alt='Tags' title='Tags' />&nbsp;";
            if (!$zoom_mode) {
                echo "<span id=\"ATSTR-$id\">$tags_str</span>";
                echo "<a title=\"";
                echo __('Edit tags for this article');
                echo "\" href=\"#\" onclick=\"editArticleTags($id, $feed_id)\">(+)</a>";
                echo "<div data-dojo-type=\"dijit.Tooltip\"
                    id=\"ATSTRTIP-$id\" connectId=\"ATSTR-$id\"
                    position=\"below\">$tags_str_full</div>";
                \SmallSmallRSS\PluginHost::getInstance()->runHooks(
                    \SmallSmallRSS\Hooks::RENDER_ARTICLE_BUTTON,
                    $line
                );

            } else {
                $tags_str = strip_tags($tags_str);
                echo "<span id=\"ATSTR-$id\">$tags_str</span>";
            }
            echo '</div>';
            echo "<div clear='both'>";
            \SmallSmallRSS\PluginHost::getInstance()->runHooks(
                \SmallSmallRSS\Hooks::RENDER_ARTICLE_LEFT_BUTTON,
                $line
            );

            echo "$entry_comments</div>";

            if ($line['orig_feed_id']) {

                $tmp_result = \SmallSmallRSS\Database::query(
                    'SELECT * FROM ttrss_archived_feeds
                 WHERE id = '.$line['orig_feed_id']
                );

                if (\SmallSmallRSS\Database::num_rows($tmp_result) != 0) {

                    echo "<div clear='both'>";
                    echo __('Originally from:');

                    echo '&nbsp;';

                    $tmp_line = \SmallSmallRSS\Database::fetch_assoc($tmp_result);

                    echo "<a target='_blank'
                        href=' " . htmlspecialchars($tmp_line['site_url']) . "'>" .
                        $tmp_line['title'] . '</a>';

                    echo '&nbsp;';

                    echo "<a target='_blank' href='" . htmlspecialchars($tmp_line['feed_url']) . "'>";
                    echo "<img title='".__('Feed URL')."'class='tinyFeedIcon' src='images/pub_set.svg'></a>";

                    echo '</div>';
                }
            }
            echo '</div>';
            echo "<div id=\"POSTNOTE-$id\">";
            if ($line['note']) {
                echo format_article_note($id, $line['note'], !$zoom_mode);
            }
            echo '</div>';
            echo '<div class="postContent">';
            echo $line['content'];
            echo format_article_enclosures(
                $id,
                \SmallSmallRSS\Database::fromSQLBool($line['always_display_enclosures']),
                $line['content'],
                \SmallSmallRSS\Database::fromSQLBool($line['hide_images'])
            );
            echo '</div>';
            echo '</div>';
        }

        if ($zoom_mode) {
            echo '<div class="footer">';
            echo "<button onclick=\"return window.close()\">";
            echo __('Close this window');
            echo '</button>';
            echo '</div>';
            echo '</body>';
            echo '</html>';
        }
        $rv['content'] = ob_get_clean();
        return $rv;
    }
}
