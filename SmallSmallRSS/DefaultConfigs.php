<?php
namespace SmallSmallRSS;

class DefaultConfigs {
    // *******************************************
    // *** Database configuration (important!) ***
    // *******************************************

    const DB_TYPE = 'pgsql'; // or mysql
    const DB_HOST = 'localhost';
    const DB_USER = 'root';
    const DB_NAME = 'tinytinyrss';
    const DB_PASS = '';
    const DB_PORT = ''; // usually 5432 for PostgreSQL, 3306 for MySQL

    // Connection charset for MySQL. If you have a legacy database and/or experience
    // garbage unicode characters with this option, try setting it to a blank string.
    const MYSQL_CHARSET = 'UTF8';

    // ***********************************
    // *** Basic settings (important!) ***
    // ***********************************

    // Full URL of your tt-rss installation. This should be set to the
    // location of tt-rss directory, e.g. http://yourserver/tt-rss/
    // You need to set this option correctly otherwise several features
    // including PUSH, bookmarklets and browser integration will not work properly.
    const SELF_URL_PATH = 'http://yourserver/tt-rss/';

    // Key used for encryption of passwords for password-protected feeds
    // in the database. A string of 24 random characters. If left blank, encryption
    // is not used. Requires mcrypt functions.
    // Warning: changing this key will make your stored feed passwords impossible
    // to decrypt.
    const FEED_CRYPT_KEY = '';

    // Operate in single user mode, disables all functionality related to
    // multiple users and authentication. Enabling this assumes you have
    // your tt-rss directory protected by other means (e.g. http auth).
    const SINGLE_USER_MODE = false;

    // Enables fallback update mode where tt-rss tries to update feeds in
    // background while tt-rss is open in your browser.
    // If you don't have a lot of feeds and don't want to or can't run
    // background processes while not running tt-rss, this method is generally
    // viable to keep your feeds up to date.
    // Still, there are more robust (and recommended) updating methods
    // available, you can read about them here: http://tt-rss.org/wiki/UpdatingFeeds
    const SIMPLE_UPDATE_MODE = false;

    // *****************************
    // *** Files and directories ***
    // *****************************

    // Path to PHP *COMMAND LINE* executable, used for various command-line tt-rss programs and
    // update daemon. Do not try to use CGI binary here, it won't work. If you see HTTP headers
    // being displayed while running tt-rss scripts, then most probably you are using the CGI
    // binary. If you are unsure what to put in here, ask your hosting provider.
    const PHP_EXECUTABLE = '/usr/bin/php';

    // Directory for lockfiles, must be writable to the user you run
    // daemon process or cronjobs under.
    const LOCK_DIRECTORY = 'lock';

    // Local cache directory for RSS feed content.
    const CACHE_DIR = 'cache';

    // Local path to the directory, where feed favicons are stored.
    // Unless you really know what you're doing, please keep those relative
    // to tt-rss main directory.
    const ICONS_DIR = 'feed-icons';

    // URL path to the directory, where feed favicons are stored.
    // Unless you really know what you're doing, please keep those relative
    // to tt-rss main directory.
    const ICONS_URL = 'feed-icons';

    // **********************
    // *** Authentication ***
    // **********************

    // Please see PLUGINS below to configure various authentication modules.

    // Allow authentication modules to auto-create users in tt-rss internal
    // database when authenticated successfully.
    const AUTH_AUTO_CREATE = true;

    // Automatically login user on remote or other kind of externally supplied
    // authentication, otherwise redirect to login form as normal.
    // If set to true, users won't be able to set application language
    // and settings profile.
    const AUTH_AUTO_LOGIN = true;

    // *********************
    // *** Feed settings ***
    // *********************

    // When this option is not 0, users ability to control feed purging
    // intervals is disabled and all articles (which are not starred)
    // older than this amount of days are purged.
    const FORCE_ARTICLE_PURGE = 0;

    // *** PubSubHubbub settings ***

    // URL to a PubSubHubbub-compatible hub server. If defined, "Published
    // articles" generated feed would automatically become PUSH-enabled.
    const PUBSUBHUBBUB_HUB = '';

    // Enable client PubSubHubbub support in tt-rss. When disabled, tt-rss
    // won't try to subscribe to PUSH feed updates.
    const PUBSUBHUBBUB_ENABLED = false;

    // *********************
    // *** Sphinx search ***
    // *********************

    // Enable fulltext search using Sphinx (http://www.sphinxsearch.com)
    // Please see http://tt-rss.org/wiki/SphinxSearch for more information.
    const SPHINX_ENABLED = false;

    // Hostname:port combination for the Sphinx server.
    const SPHINX_SERVER = 'localhost:9312';

    // Index name in Sphinx configuration. You can specify multiple indexes
    // as a comma-separated string.
    // Example configuration files are available on tt-rss wiki.
    const SPHINX_INDEX = 'ttrss, delta';

    // ***********************************
    // *** Self-registrations by users ***
    // ***********************************

    // Allow users to register themselves. Please be aware that allowing
    // random people to access your tt-rss installation is a security risk
    // and potentially might lead to data loss or server exploit. Disabled
    // by default.
    const ENABLE_REGISTRATION = false;

    // Email address to send new user notifications to.
    const REG_NOTIFY_ADDRESS = '';

    // Maximum amount of users which will be allowed to register on this
    // system. 0 - no limit.
    const REG_MAX_USERS = 10;

    // **********************************
    // *** Cookies and login sessions ***
    // **********************************

    // Default lifetime of a session (e.g. login) cookie. In seconds,
    // 0 means cookie will be deleted when browser closes.
    const SESSION_COOKIE_LIFETIME = 86400;

    // Check client IP address when validating session:
    // 0 - disable checking
    // 1 - check first 3 octets of an address (recommended)
    // 2 - check first 2 octets of an address
    // 3 - check entire address
    const SESSION_CHECK_ADDRESS = 1;

    // *********************************
    // *** Email and digest settings ***
    // *********************************

    // Name, address and subject for sending outgoing mail. This applies
    // to password reset notifications, digest emails and any other mail.
    const SMTP_FROM_NAME = 'Tiny Tiny RSS';
    const SMTP_FROM_ADDRESS = 'noreply@example.com';
    // Subject line for email digests
    const DIGEST_SUBJECT = '[tt-rss] New headlines for last 24 hours';

    // Hostname:port combination to send outgoing mail (i.e. localhost:25).
    // Blank - use system MTA.
    const SMTP_SERVER = '';

    // These two options enable SMTP authentication when sending
    // outgoing mail. Only used with SMTP_SERVER.
    const SMTP_LOGIN = '';
    const SMTP_PASSWORD = '';

    // used to select a secure SMTP conneciton.  can be tls, ssl or empty
    const SMTP_SECURE = '';

    // ***************************************
    // *** Other settings (less important) ***
    // ***************************************

    // Comma-separated list of plugins to load automatically for all users.
    // System plugins have to be specified here. Please enable at least one
    // authentication plugin here (auth_*).
    // Users may enable other user plugins from Preferences/Plugins but may not
    // disable plugins specified in this list.
    // Disabling auth_internal in this list would automatically disable
    // reset password link on the login form.
    const PLUGINS = 'auth_internal, note';

    // Log destination to use. Possible values: sql (uses internal logging
    // you can read in Preferences -> System), syslog - logs to system log.
    // Setting this to blank uses PHP logging (usually to http server
    // error.log).
    const LOG_DESTINATION = 'sql';

    /**
     * Expected config version. Please update this option in config.php
     * if necessary (after migrating all new options from this file).
     */
    const CONFIG_VERSION = 26;

    /**
     * How verbose to be.
     */
    const VERBOSITY = 0;

    const DAEMON_UPDATE_LOGIN_LIMIT = 30;
    const DAEMON_FEED_LIMIT = 50;
    const DAEMON_SLEEP_INTERVAL = 120;
    const SPAWN_INTERVAL = 120; // seconds

    /**
     * How many seconds to wait for response when requesting feed from a site
     */
    const FEED_FETCH_TIMEOUT = 45;
    /**
     * How many seconds to wait for response when requesting feed from a site
     * when that feed was not cached before
     */
    const FEED_FETCH_NO_CACHE_TIMEOUT = 15;
    /**
     * Default timeout when fetching files from remote sites
     */
    const FILE_FETCH_TIMEOUT = 45;

    /**
     * How many seconds to wait for initial response from website when fetching
     * files from remote sites
     */
    const FILE_FETCH_CONNECT_TIMEOUT = 15;

    const PURGE_INTERVAL = 3600; // seconds
    const MAX_CHILD_RUNTIME = 1800; // seconds
    const MAX_JOBS = 2;

    const AUTH_DISABLE_OTP = false;
    const ENABLE_PDO = false;

    /**
     * Should the application reload if the timestamp changes on any
     * javascript file?
     */
    const RELOAD_ON_TS_CHANGE = false;

    /**
     * Override the default translation with this
     * example: en_US
     */
    const FORCED_LOCALE = '';

    // TODO: Swap these out as well
    const _API_DEBUG_HTTP_ENABLED = false;
    const _ENABLE_FEED_DEBUGGING = false;
    const _DISABLE_FEED_BROWSER = false;
    const _DISABLE_HTTP_304 = false;
    const _NGRAM_TITLE_DUPLICATE_THRESHOLD = false;
    const DAEMON_EXTENDED_DEBUG = false;
    const _INSTALLER_IGNORE_CONFIG_CHECK = false;

    public static function has($key) {
        return defined("self::$key");
    }
    public static function get($key) {
        return constant("self::$key");
    }
    public static function type($key) {
        $type = 'string';
        if (self::has($key)) {
            $type = gettype(self::get($key));
        }
        switch ($type) {
            case 'double':
                $type = 'float';
                break;
            case 'integer':
                break;
            case 'boolean':
                break;
            default:
                $type = 'string';
                break;
        }
        return $type;
    }
}