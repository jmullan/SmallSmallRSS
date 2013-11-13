<?php
namespace SmallSmallRSS;

class Fetcher
{

    public function __construct()
    {
        $this->fetch_curl_used = false;
        $this->fetch_last_error = false;
        $this->fetch_last_error_code = false;
        $this->fetch_last_content_code = false;
    }

    public function fetch(
        $url,
        $type = false,
        $login = false,
        $pass = false,
        $post_query = false,
        $timeout = false,
        $timestamp = 0
    ) {
        $fetcher = new self();
        return $fetcher->getFileContents($url, $type, $login, $pass, $post_query, $timeout, $timestamp);
    }

    public function getFileContents(
        $url,
        $type = false,
        $login = false,
        $pass = false,
        $post_query = false,
        $timeout = false,
        $timestamp = 0
    ) {
        $url = str_replace(' ', '%20', $url);
        if (!defined('NO_CURL') && function_exists('curl_init')) {
            $this->fetch_curl_used = true;
            if (ini_get("safe_mode") || ini_get("open_basedir")) {
                $new_url = $this->curlResolveUrl($url);
                if (!$new_url) {
                    // geturl has already populated $this->fetch_last_error
                    return false;
                }
                $ch = curl_init($new_url);
            } else {
                $ch = curl_init($url);
            }
            if ($timestamp && !$post_query) {
                curl_setopt(
                    $ch,
                    CURLOPT_HTTPHEADER,
                    array("If-Modified-Since: ".gmdate('D, d M Y H:i:s \G\M\T', $timestamp))
                );
            }
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout ? $timeout : FILE_FETCH_CONNECT_TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout ? $timeout : FILE_FETCH_TIMEOUT);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, !ini_get("safe_mode") && !ini_get("open_basedir"));
            curl_setopt($ch, CURLOPT_MAXREDIRS, 20);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERAGENT, SELF_USER_AGENT);
            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_REFERER, $url);

            if ($post_query) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_query);
            }

            if ($login && $pass) {
                curl_setopt($ch, CURLOPT_USERPWD, "$login:$pass");
            }
            $contents = @curl_exec($ch);

            if (curl_errno($ch) === 23 || curl_errno($ch) === 61) {
                curl_setopt($ch, CURLOPT_ENCODING, 'none');
                $contents = @curl_exec($ch);
            }
            if ($contents === false) {
                $this->fetch_last_error = curl_errno($ch) . " " . curl_error($ch);
                curl_close($ch);
                return false;
            }

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->fetch_last_content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            $this->fetch_last_error_code = $http_code;

            if ($http_code != 200 || $type && strpos($this->fetch_last_content_type, "$type") === false) {
                if (curl_errno($ch) != 0) {
                    $this->fetch_last_error = curl_errno($ch) . " " . curl_error($ch);
                } else {
                    $this->fetch_last_error = "HTTP Code: $http_code";
                }
                curl_close($ch);
                return false;
            }

            curl_close($ch);
            return $contents;
        } else {

            $this->fetch_curl_used = false;

            if ($login && $pass) {
                $url_parts = array();

                preg_match("/(^[^:]*):\/\/(.*)/", $url, $url_parts);

                $pass = urlencode($pass);

                if ($url_parts[1] && $url_parts[2]) {
                    $url = $url_parts[1] . "://$login:$pass@" . $url_parts[2];
                }
            }

            if (!$post_query && $timestamp) {
                $context = stream_context_create(
                    array(
                        'http' => array(
                            'method' => 'GET',
                            'header' => "If-Modified-Since: " . gmdate("D, d M Y H:i:s \\G\\M\\T\r\n", $timestamp)
                        )
                    )
                );
            } else {
                $context = null;
            }

            $old_error = error_get_last();

            $data = @file_get_contents($url, false, $context);

            $this->fetch_last_content_type = false; // reset if no type was sent from server
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (substr(strtolower($h), 0, 13) == 'content-type:') {
                        $this->fetch_last_content_type = substr($h, 14);
                        // don't abort here b/c there might be more than one
                        // e.g. if we were being redirected -- last one is the right one
                    }

                    if (substr(strtolower($h), 0, 7) == 'http/1.') {
                        $this->fetch_last_error_code = (int) substr($h, 9, 3);
                    }
                }
            }

            if (!$data) {
                $error = error_get_last();

                if ($error['message'] != $old_error['message']) {
                    $this->fetch_last_error = $error["message"];
                } else {
                    $this->fetch_last_error = "HTTP Code: $this->fetch_last_error_code";
                }
            }
            $tmp = @gzdecode($data);
            if ($tmp) {
                $data = $tmp;
            }
            return $data;
        }
    }

    public function curlResolveUrl($url)
    {
        if (!function_exists('curl_init')) {
            return user_error(
                'CURL Must be installed for geturl function to work.'
                . ' Ask your host to enable it or uncomment extension=php_curl.dll in php.ini',
                E_USER_ERROR
            );
        }

        $curl = curl_init();
        $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Pragma: ";

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt(
            $curl,
            CURLOPT_USERAGENT,
            'Mozilla/5.0 (Windows NT 5.1; rv:5.0) Gecko/20100101 Firefox/5.0 Firefox/5.0'
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_REFERER, $url);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); //CURLOPT_FOLLOWLOCATION Disabled...
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $html = curl_exec($curl);

        $status = curl_getinfo($curl);

        if ($status['http_code'] != 200) {
            if ($status['http_code'] == 301 || $status['http_code'] == 302) {
                curl_close($curl);
                list($header) = explode("\r\n\r\n", $html, 2);
                $matches = array();
                preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
                $url = trim(str_replace($matches[1], "", $matches[0]));
                $url_parsed = parse_url($url);
                return (isset($url_parsed))? geturl($url):'';
            }

            $this->fetch_last_error = curl_errno($curl) . " " . curl_error($curl);
            curl_close($curl);

            $oline = '';
            foreach ($status as $key => $eline) {
                $oline .= '['.$key.']'.$eline.' ';
            }
            $line = $oline." \r\n ".$url."\r\n-----------------\r\n";
            return false;
        }
        curl_close($curl);
        return $url;
    }
}
