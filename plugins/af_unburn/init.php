<?php
class Af_Unburn extends \SmallSmallRSS\Plugin {
    private $host;

    function about() {
        return array(
            1.0,
            "Resolves feedburner and similar feed redirector URLs (requires CURL)",
            "fox");
    }

    function init($host) {
        $this->host = $host;

        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
    }

    function hook_article_filter($article) {
        $owner_uid = $article["owner_uid"];

        if (!function_exists("curl_init"))
            return $article;

        if ((strpos($article["link"], "feedproxy.google.com") !== FALSE ||
             strpos($article["link"], "/~r/") !== FALSE ||
             strpos($article["link"], "feedsportal.com") !== FALSE)) {

            if (strpos($article["plugin_data"], "unburn,$owner_uid:") === FALSE) {

                if (ini_get("safe_mode") || ini_get("open_basedir")) {
                    $fetcher = new \SmallSmallRSS\Fetch();
                    $url = $fetcher->curl_resolve_url($article["link"]);
                } else {
                    $ch = curl_init($article["link"]);
                }

                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, !ini_get("safe_mode") && !ini_get("open_basedir"));
                curl_setopt($ch, CURLOPT_USERAGENT, SELF_USER_AGENT);

                $contents = @curl_exec($ch);

                $real_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

                curl_close($ch);

                if ($real_url) {
                    /* remove the rest of it */

                    $query = parse_url($real_url, PHP_URL_QUERY);

                    if ($query && strpos($query, "utm_source") !== FALSE) {
                        $args = array();
                        parse_str($query, $args);

                        foreach (array("utm_source", "utm_medium", "utm_campaign") as $param) {
                            if (isset($args[$param])) unset($args[$param]);
                        }

                        $new_query = http_build_query($args);

                        if ($new_query != $query) {
                            $real_url = str_replace("?$query", "?$new_query", $real_url);
                        }
                    }

                    $real_url = preg_replace("/\?$/", "", $real_url);

                    $article["plugin_data"] = "unburn,$owner_uid:" . $article["plugin_data"];
                    $article["link"] = $real_url;
                }
            } else if (isset($article["stored"]["link"])) {
                $article["link"] = $article["stored"]["link"];
            }
        }

        return $article;
    }

    function api_version() {
        return 2;
    }
}