<?php
namespace SmallSmallRSS;

class Digest
{
    /**
     * Send by mail a digest of last articles.
     *
     * @param mixed $link The database connection.
     * @param integer $limit The maximum number of articles by digest.
     * @return boolean Return false if digests are not enabled.
     */
    function send_headlines($debug = false)
    {

        $user_limit = 15; // amount of users to process (e.g. emails to send out)
        $limit = 1000; // maximum amount of headlines to include

        _debug("Sending digests, batch of max $user_limit users, headline limit = $limit", true, $debug);

        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $interval_query = "last_digest_sent < NOW() - INTERVAL '1 days'";
        } elseif (\SmallSmallRSS\Config::get('DB_TYPE') == "mysql") {
            $interval_query = "last_digest_sent < DATE_SUB(NOW(), INTERVAL 1 DAY)";
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT id,email FROM ttrss_users
                WHERE email != '' AND (last_digest_sent IS NULL OR $interval_query)"
        );

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            if (@\SmallSmallRSS\DBPrefs::read('DIGEST_ENABLE', $line['id'], false)) {
                $preferred_ts = strtotime(\SmallSmallRSS\DBPrefs::read('DIGEST_PREFERRED_TIME', $line['id'], '00:00'));
                $since = time() - $preferred_ts;
                // try to send digests within 2 hours of preferred time
                if ($preferred_ts && $since >= 0 && $since < 7200) {
                    _debug("Sending digest for UID:" . $line['id'] . " - " . $line["email"], true, $debug);
                    $do_catchup = \SmallSmallRSS\DBPrefs::read('DIGEST_CATCHUP', $line['id'], false);
                    $tuple = prepare_headlines_digest($line["id"], 1, $limit);
                    $digest = $tuple[0];
                    $headlines_count = $tuple[1];
                    $affected_ids = $tuple[2];
                    $digest_text = $tuple[3];

                    if ($headlines_count > 0) {

                        $mail = new \SmallSmallRSS\Mailer();

                        $rc = $mail->quickMail(
                            $line["email"],
                            $line["login"],
                            \SmallSmallRSS\Config::get('DIGEST_SUBJECT'),
                            $digest,
                            $digest_text
                        );
                        if (!$rc) {
                            _debug("ERROR: " . $mail->ErrorInfo, true, $debug);
                        }
                        _debug("RC=$rc", true, $debug);
                        if ($rc && $do_catchup) {
                            _debug("Marking affected articles as read...", true, $debug);
                            catchupArticlesById($affected_ids, 0, $line["id"]);
                        }
                    } else {
                        _debug("No headlines", true, $debug);
                    }
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_users SET last_digest_sent = NOW()
                        WHERE id = " . $line["id"]
                    );

                }
            }
        }
        _debug("All done.", true, $debug);

    }

    function prepare_headlines($user_id, $days = 1, $limit = 1000)
    {

        require_once "lib/MiniTemplator.class.php";

        $tpl = new MiniTemplator;
        $tpl_t = new MiniTemplator;

        $tpl->readTemplateFromFile("templates/digest_template_html.txt");
        $tpl_t->readTemplateFromFile("templates/digest_template.txt");

        $user_tz_string = \SmallSmallRSS\DBPrefs::read('USER_TIMEZONE', $user_id);
        $local_ts = convert_timestamp(time(), 'UTC', $user_tz_string);

        $tpl->setVariable('CUR_DATE', date('Y/m/d', $local_ts));
        $tpl->setVariable('CUR_TIME', date('G:i', $local_ts));

        $tpl_t->setVariable('CUR_DATE', date('Y/m/d', $local_ts));
        $tpl_t->setVariable('CUR_TIME', date('G:i', $local_ts));

        $affected_ids = array();

        if (\SmallSmallRSS\Config::get('DB_TYPE') == "pgsql") {
            $interval_query = "ttrss_entries.date_updated > NOW() - INTERVAL '$days days'";
        } elseif (\SmallSmallRSS\Config::get('DB_TYPE') == "mysql") {
            $interval_query = "ttrss_entries.date_updated > DATE_SUB(NOW(), INTERVAL $days DAY)";
        }

        $result = \SmallSmallRSS\Database::query(
            "SELECT ttrss_entries.title,
                ttrss_feeds.title AS feed_title,
                COALESCE(ttrss_feed_categories.title, '".__('Uncategorized')."') AS cat_title,
                date_updated,
                ttrss_user_entries.ref_id,
                link,
                score,
                content,
                ".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated
            FROM
                ttrss_user_entries,ttrss_entries,ttrss_feeds
            LEFT JOIN
                ttrss_feed_categories ON (cat_id = ttrss_feed_categories.id)
            WHERE
                ref_id = ttrss_entries.id AND feed_id = ttrss_feeds.id
                AND include_in_digest = true
                AND $interval_query
                AND ttrss_user_entries.owner_uid = $user_id
                AND unread = true
                AND score >= 0
            ORDER BY ttrss_feed_categories.title, ttrss_feeds.title, score DESC, date_updated DESC
            LIMIT $limit"
        );

        $cur_feed_title = "";

        $headlines_count = \SmallSmallRSS\Database::num_rows($result);

        $headlines = array();

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {
            array_push($headlines, $line);
        }

        for ($i = 0; $i < sizeof($headlines); $i++) {

            $line = $headlines[$i];

            array_push($affected_ids, $line["ref_id"]);

            $updated = make_local_datetime(
                $line['last_updated'], false,
                $user_id
            );

            if (\SmallSmallRSS\DBPrefs::read('ENABLE_FEED_CATS', $user_id)) {
                $line['feed_title'] = $line['cat_title'] . " / " . $line['feed_title'];
            }

            $tpl->setVariable('FEED_TITLE', $line["feed_title"]);
            $tpl->setVariable('ARTICLE_TITLE', $line["title"]);
            $tpl->setVariable('ARTICLE_LINK', $line["link"]);
            $tpl->setVariable('ARTICLE_UPDATED', $updated);
            $tpl->setVariable(
                'ARTICLE_EXCERPT',
                truncate_string(strip_tags($line["content"]), 300)
            );
            //            $tpl->setVariable('ARTICLE_CONTENT',
            //                strip_tags($article_content));

            $tpl->addBlock('article');

            $tpl_t->setVariable('FEED_TITLE', $line["feed_title"]);
            $tpl_t->setVariable('ARTICLE_TITLE', $line["title"]);
            $tpl_t->setVariable('ARTICLE_LINK', $line["link"]);
            $tpl_t->setVariable('ARTICLE_UPDATED', $updated);
            //            $tpl_t->setVariable('ARTICLE_EXCERPT',
            //                truncate_string(strip_tags($line["excerpt"]), 100));

            $tpl_t->addBlock('article');

            if ($headlines[$i]['feed_title'] != $headlines[$i+1]['feed_title']) {
                $tpl->addBlock('feed');
                $tpl_t->addBlock('feed');
            }

        }

        $tpl->addBlock('digest');
        $tpl->generateOutputToString($tmp);

        $tpl_t->addBlock('digest');
        $tpl_t->generateOutputToString($tmp_t);

        return array($tmp, $headlines_count, $affected_ids, $tmp_t);
    }
}
