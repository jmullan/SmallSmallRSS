<?php
namespace SmallSmallRSS\Handlers;

class Pref_Prefs extends ProtectedHandler
{

    private $pref_help = array();
    private $pref_sections = array();

    public function ignoreCSRF($method)
    {
        $csrf_ignored = array("index", "updateself", "customizecss", "editprefprofiles");

        return array_search($method, $csrf_ignored) !== false;
    }

    public function __construct($args)
    {
        parent::__construct($args);

        $this->pref_sections = array(
            1 => __('General'),
            2 => __('Interface'),
            3 => __('Advanced'),
            4 => __('Digest')
        );

        $this->pref_help = array(
            "ALLOW_DUPLICATE_POSTS" => array(__("Allow duplicate articles"), ""),
            "AUTO_ASSIGN_LABELS" => array(__("Assign articles to labels automatically"), ""),
            "BLACKLISTED_TAGS" => array(
                __("Blacklisted tags"),
                __("When auto-detecting tags in articles these tags will not be applied (comma-separated list).")
            ),
            "CDM_AUTO_CATCHUP" => array(
                __("Automatically mark articles as read"),
                __("This option enables marking articles as read automatically while you scroll article list.")
            ),
            "CDM_EXPANDED" => array(__("Automatically expand articles in combined mode"), ""),
            "COMBINED_DISPLAY_MODE" => array(
                __("Combined feed display"),
                __("Display expanded list of feed articles, instead of separate displays for headlines and article content")
            ),
            "CONFIRM_FEED_CATCHUP" => array(__("Confirm marking feed as read"), ""),
            "DEFAULT_ARTICLE_LIMIT" => array(__("Amount of articles to display at once"), ""),
            "DEFAULT_UPDATE_INTERVAL" => array(
                __("Default feed update interval"),
                __("Shortest interval at which a feed will be checked for updates regardless of update method")
            ),
            "DIGEST_CATCHUP" => array(__("Mark articles in e-mail digest as read"), ""),
            "DIGEST_ENABLE" => array(
                __("Enable e-mail digest"),
                __("This option enables sending daily digest of new (and unread) headlines on your configured e-mail address")
            ),
            "DIGEST_PREFERRED_TIME" => array(
                __("Try to send digests around specified time"),
                __("Uses UTC timezone")
            ),
            "ENABLE_API_ACCESS" => array(
                __("Enable API access"),
                __("Allows external clients to access this account through the API")
            ),
            "ENABLE_FEED_CATS" => array(__("Enable feed categories"), ""),
            "FEEDS_SORT_BY_UNREAD" => array(__("Sort feeds by unread articles count"), ""),
            "FRESH_ARTICLE_MAX_AGE" => array(__("Maximum age of fresh articles (in hours)"), ""),
            "HIDE_READ_FEEDS" => array(__("Hide feeds with no unread articles"), ""),
            "HIDE_READ_SHOWS_SPECIAL" => array(__("Show special feeds when hiding read feeds"), ""),
            "LONG_DATE_FORMAT" => array(
                __("Long date format"),
                __("The syntax used is identical to the PHP <a href='http://php.net/manual/function.date.php'>date()</a> function.")
            ),
            "ON_CATCHUP_SHOW_NEXT_FEED" => array(
                __("On catchup show next feed"),
                __("Automatically open next feed with unread articles after marking one as read")
            ),
            "PURGE_OLD_DAYS" => array(__("Purge articles after this number of days (0 - disables)"), ""),
            "PURGE_UNREAD_ARTICLES" => array(__("Purge unread articles"), ""),
            "REVERSE_HEADLINES" => array(__("Reverse headline order (oldest first)"), ""),
            "SHORT_DATE_FORMAT" => array(__("Short date format"), ""),
            "SHOW_CONTENT_PREVIEW" => array(__("Show content preview in headlines list"), ""),
            "SORT_HEADLINES_BY_FEED_DATE" => array(
                __("Sort headlines by feed date"),
                __("Use feed-specified date to sort headlines instead of local import date.")
            ),
            "SSL_CERT_SERIAL" => array(
                __("Login with an SSL certificate"),
                __("Click to register your SSL client certificate with tt-rss")
            ),
            "STRIP_IMAGES" => array(__("Do not embed images in articles"), ""),
            "STRIP_UNSAFE_TAGS" => array(
                __("Strip unsafe tags from articles"),
                __("Strip all but most common HTML tags when reading articles.")
            ),
            "USER_STYLESHEET" => array(__("Customize stylesheet"), __("Customize CSS stylesheet to your liking")),
            "USER_TIMEZONE" => array(__("Time zone"), ""),
            "VFEED_GROUP_BY_FEED" => array(
                __("Group headlines in virtual feeds"),
                __("Special feeds, labels, and categories are grouped by originating feeds")
            ),
            "USER_LANGUAGE" => array(__("Language")),
            "USER_CSS_THEME" => array(__("Theme"), __("Select one of the available CSS themes"))
        );
    }

    public function changepassword()
    {

        $old_pw = $_POST["old_password"];
        $new_pw = $_POST["new_password"];
        $con_pw = $_POST["confirm_password"];

        if ($old_pw == "") {
            print "ERROR: ".__("Old password cannot be blank.");
            return;
        }

        if ($new_pw == "") {
            print "ERROR: ".__("New password cannot be blank.");
            return;
        }

        if ($new_pw != $con_pw) {
            print "ERROR: ".__("Entered passwords do not match.");
            return;
        }

        $authenticator = \SmallSmallRSS\PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);

        if (method_exists($authenticator, "change_password")) {
            print $authenticator->change_password($_SESSION["uid"], $old_pw, $new_pw);
        } else {
            print "ERROR: ".__("Function not supported by authentication module.");
        }
    }

    public function saveconfig()
    {
        $boolean_prefs = explode(",", $_POST["boolean_prefs"]);
        foreach ($boolean_prefs as $pref) {
            if (!isset($_POST[$pref])) {
                $_POST[$pref] = 'false';
            }
        }
        $need_reload = false;
        foreach (array_keys($_POST) as $pref_name) {
            $pref_name = \SmallSmallRSS\Database::escape_string($pref_name);
            $value = \SmallSmallRSS\Database::escape_string($_POST[$pref_name]);
            if ($pref_name == 'DIGEST_PREFERRED_TIME') {
                if (\SmallSmallRSS\DBPrefs::read('DIGEST_PREFERRED_TIME') != $value) {
                    \SmallSmallRSS\Database::query(
                        "UPDATE ttrss_users SET
                        last_digest_sent = NULL WHERE id = " . $_SESSION['uid']
                    );
                }
            }
            if ($pref_name == "USER_LANGUAGE") {
                if ($_SESSION["language"] != $value) {
                    $need_reload = true;
                }
            }
            \SmallSmallRSS\DBPrefs::write($pref_name, $value);
        }
        if ($need_reload) {
            print "PREFS_NEED_RELOAD";
        } else {
            print __("The configuration was saved.");
        }
    }

    public function getHelp()
    {
        $pref_name = \SmallSmallRSS\Database::escape_string($_REQUEST["pn"]);
        $result = \SmallSmallRSS\Database::query(
            "SELECT help_text FROM ttrss_prefs
            WHERE pref_name = '$pref_name'"
        );
        if (\SmallSmallRSS\Database::num_rows($result) > 0) {
            $help_text = \SmallSmallRSS\Database::fetch_result($result, 0, "help_text");
            print $help_text;
        } else {
            printf(__("Unknown option: %s"), $pref_name);
        }
    }

    public function changeemail()
    {
        $email = \SmallSmallRSS\Database::escape_string($_POST["email"]);
        $full_name = \SmallSmallRSS\Database::escape_string($_POST["full_name"]);
        $active_uid = $_SESSION["uid"];
        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_users SET email = '$email',
            full_name = '$full_name' WHERE id = '$active_uid'"
        );
        print __("Your personal data has been saved.");
    }

    public function resetconfig()
    {
        $_SESSION["prefs_op_result"] = "reset-to-defaults";
        if ($_SESSION["profile"]) {
            $profile_qpart = "profile = '" . $_SESSION["profile"] . "'";
        } else {
            $profile_qpart = "profile IS NULL";
        }
        \SmallSmallRSS\Database::query(
            "DELETE FROM ttrss_user_prefs
            WHERE $profile_qpart AND owner_uid = ".$_SESSION["uid"]
        );
        initialize_user_prefs($_SESSION["uid"], $_SESSION["profile"]);
        echo __("Your preferences are now set to default values.");
    }

    public function index()
    {
        $form_element_renderer = new \SmallSmallRSS\Renderers\FormElements();
        $access_level_names = \SmallSmallRSS\Constants::access_level_names();
        $prefs_blacklist = array("STRIP_UNSAFE_TAGS", "REVERSE_HEADLINES",
                                 "SORT_HEADLINES_BY_FEED_DATE", "DEFAULT_ARTICLE_LIMIT");

        /* "FEEDS_SORT_BY_UNREAD", "HIDE_READ_FEEDS", "REVERSE_HEADLINES" */

        $profile_blacklist = array(
            "ALLOW_DUPLICATE_POSTS",
            "PURGE_OLD_DAYS",
            "PURGE_UNREAD_ARTICLES",
            "DIGEST_ENABLE",
            "DIGEST_CATCHUP",
            "BLACKLISTED_TAGS",
            "ENABLE_API_ACCESS",
            "UPDATE_POST_ON_CHECKSUM_CHANGE",
            "DEFAULT_UPDATE_INTERVAL",
            "USER_TIMEZONE",
            "SORT_HEADLINES_BY_FEED_DATE",
            "SSL_CERT_SERIAL",
            "DIGEST_PREFERRED_TIME"
        );


        $_SESSION["prefs_op_result"] = "";

        print "<div dojoType=\"dijit.layout.AccordionContainer\" region=\"center\">";
        print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"";
        print __('Personal data / Authentication');
        print "\">";
        print "<form dojoType=\"dijit.form.Form\" id=\"changeUserdataForm\">";
        print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
        evt.preventDefault();
        if (this.validate()) {
            notify_progress('Saving data...', true);

            new Ajax.Request('backend.php', {
                parameters: dojo.objectToQuery(this.getValues()),
                onComplete: function(transport) {
                    notify_callback2(transport);
            } });

        }
        </script>";
        print "<table width=\"100%\" class=\"prefPrefsList\">";

        print "<h2>" . __("Personal data") . "</h2>";

        $result = \SmallSmallRSS\Database::query(
            "SELECT email,full_name,otp_enabled,
            access_level FROM ttrss_users
            WHERE id = ".$_SESSION["uid"]
        );

        $email = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, "email"));
        $full_name = htmlspecialchars(\SmallSmallRSS\Database::fetch_result($result, 0, "full_name"));
        $otp_enabled = sql_bool_to_bool(\SmallSmallRSS\Database::fetch_result($result, 0, "otp_enabled"));

        print "<tr><td width=\"40%\">".__('Full name')."</td>";
        print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\"";
        print " name=\"full_name\" required=\"1\" value=\"$full_name\"></td></tr>";

        print "<tr><td width=\"40%\">".__('E-mail')."</td>";
        print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\"";
        print " name=\"email\" required=\"1\" value=\"$email\"></td></tr>";

        if (!\SmallSmallRSS\Auth::is_single_user_mode() && empty($_SESSION["hide_hello"])) {
            $access_level = \SmallSmallRSS\Database::fetch_result($result, 0, "access_level");
            print "<tr><td width=\"40%\">".__('Access level')."</td>";
            print "<td>" . $access_level_names[$access_level] . "</td></tr>";
        }

        print "</table>";

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"pref-prefs\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"changeemail\">";

        print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
            __("Save data")."</button>";

        print "</form>";

        if ($_SESSION["auth_module"]) {
            $authenticator = \SmallSmallRSS\PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);
        } else {
            $authenticator = false;
        }

        if ($authenticator && method_exists($authenticator, "change_password")) {

            print "<h2>" . __("Password") . "</h2>";

            $result = \SmallSmallRSS\Database::query(
                "SELECT id FROM ttrss_users
                WHERE id = ".$_SESSION["uid"]." AND pwd_hash
                = 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8'"
            );

            if (\SmallSmallRSS\Database::num_rows($result) != 0) {
                \SmallSmallRSS\Renderers\Messages::renderWarning(
                    __("Your password is at default value, please change it."),
                    "default_pass_warning"
                );
            }

            print "<form dojoType=\"dijit.form.Form\">";

            print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
            evt.preventDefault();
            if (this.validate()) {
                notify_progress('Changing password...', true);

                new Ajax.Request('backend.php', {
                    parameters: dojo.objectToQuery(this.getValues()),
                    onComplete: function(transport) {
                        notify('');
                        if (transport.responseText.indexOf('ERROR: ') == 0) {
                            notify_error(transport.responseText.replace('ERROR: ', ''));
                        } else {
                            notify_info(transport.responseText);
                            var warn = $('default_pass_warning');
                            if (warn) Element.hide(warn);
                        }
                }});
                this.reset();
            }
            </script>";

            if ($otp_enabled) {
                \SmallSmallRSS\Renderers\Messages::renderNotice(
                    __("Changing your current password will disable OTP.")
                );
            }

            print "<table width=\"100%\" class=\"prefPrefsList\">";

            print "<tr><td width=\"40%\">".__("Old password")."</td>";
            print "<td class=\"prefValue\">";
            print "<input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\"";
            print " required=\"1\" name=\"old_password\"></td></tr>";

            print "<tr><td width=\"40%\">".__("New password")."</td>";

            print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\"";
            print " type=\"password\" required=\"1\" name=\"new_password\"></td></tr>";

            print "<tr><td width=\"40%\">".__("Confirm password")."</td>";

            print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\"";
            print " type=\"password\" required=\"1\" name=\"confirm_password\"></td></tr>";

            print "</table>";

            print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\"";
            print " name=\"op\" value=\"pref-prefs\">";
            print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\"";
            print " name=\"method\" value=\"changepassword\">";

            print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
                __("Change password")."</button>";

            print "</form>";

            if ($_SESSION["auth_module"] == "auth_internal") {

                print "<h2>" . __("One time passwords / Authenticator") . "</h2>";

                if ($otp_enabled) {

                    \SmallSmallRSS\Renderers\Messages::renderNotice(
                        __("One time passwords are currently enabled. Enter your current password below to disable.")
                    );

                    print "<form dojoType=\"dijit.form.Form\">";

                    print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
                evt.preventDefault();
                if (this.validate()) {
                    notify_progress('Disabling OTP', true);

                    new Ajax.Request('backend.php', {
                        parameters: dojo.objectToQuery(this.getValues()),
                        onComplete: function(transport) {
                            notify('');
                            if (transport.responseText.indexOf('ERROR: ') == 0) {
                                notify_error(transport.responseText.replace('ERROR: ', ''));
                            } else {
                                window.location.reload();
                            }
                    }});
                    this.reset();
                }
                </script>";

                    print "<table width=\"100%\" class=\"prefPrefsList\">";

                    print "<tr><td width=\"40%\">".__("Enter your password")."</td>";

                    print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\"
                    name=\"password\"></td></tr>";

                    print "</table>";

                    print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"pref-prefs\">";
                    print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"otpdisable\">";

                    print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
                        __("Disable OTP")."</button>";

                    print "</form>";

                } elseif (function_exists("imagecreatefromstring")) {

                    \SmallSmallRSS\Renderers\Messages::renderWarning(
                        __("You will need a compatible Authenticator to use this. Changing your password would automatically disable OTP.")
                    );

                    print "<p>".__("Scan the following code by the Authenticator application:")."</p>";

                    $csrf_token = $_SESSION["csrf_token"];

                    print "<img src=\"backend.php?op=pref-prefs&method=otpqrcode&csrf_token=$csrf_token\">";

                    print "<form dojoType=\"dijit.form.Form\" id=\"changeOtpForm\">";

                    print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"pref-prefs\">";
                    print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"otpenable\">";

                    print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
                    evt.preventDefault();
                    if (this.validate()) {
                        notify_progress('Saving data...', true);

                        new Ajax.Request('backend.php', {
                            parameters: dojo.objectToQuery(this.getValues()),
                            onComplete: function(transport) {
                                notify('');
                                if (transport.responseText.indexOf('ERROR:') == 0) {
                                    notify_error(transport.responseText.replace('ERROR:', ''));
                                } else {
                                    window.location.reload();
                                }
                        } });

                    }
                    </script>";

                    print "<table width=\"100%\" class=\"prefPrefsList\">";

                    print "<tr><td width=\"40%\">".__("Enter your password")."</td>";

                    print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\"
                        name=\"password\"></td></tr>";

                    print "<tr><td width=\"40%\">".__("Enter the generated one time password")."</td>";

                    print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" autocomplete=\"off\"
                        required=\"1\"
                        name=\"otp\"></td></tr>";

                    print "<tr><td colspan=\"2\">";

                    print "</td></tr><tr><td colspan=\"2\">";

                    print "</td></tr>";
                    print "</table>";

                    print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
                        __("Enable OTP")."</button>";

                    print "</form>";

                } else {

                    \SmallSmallRSS\Renderers\Messages::renderNotice(
                        __("PHP GD functions are required for OTP support.")
                    );

                }

            }
        }

        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::PREFS_TAB_SECTION,
            "prefPrefsAuth"
        );

        print "</div>"; #pane

        print "<div dojoType=\"dijit.layout.AccordionPane\" selected=\"true\" title=\"".__('Preferences')."\">";

        print "<form dojoType=\"dijit.form.Form\" id=\"changeSettingsForm\">";

        print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt, quit\">
        if (evt) evt.preventDefault();
        if (this.validate()) {
            console.log(dojo.objectToQuery(this.getValues()));

            new Ajax.Request('backend.php', {
                parameters: dojo.objectToQuery(this.getValues()),
                onComplete: function(transport) {
                    var msg = transport.responseText;
                    if (quit) {
                        gotoMain();
                    } else {
                        if (msg == 'PREFS_NEED_RELOAD') {
                            window.location.reload();
                        } else {
                            notify_info(msg);
                        }
                    }
            } });
        }
        </script>";

        print '<div dojoType="dijit.layout.BorderContainer" gutters="false">';

        print '<div dojoType="dijit.layout.ContentPane" region="center" style="overflow-y : auto">';

        if (!empty($_SESSION["profile"])) {
            \SmallSmallRSS\Renderers\Messages::renderNotice(
                __("Some preferences are only available in default profile.")
            );
        }

        if (!empty($_SESSION["profile"])) {
            initialize_user_prefs($_SESSION["uid"], $_SESSION["profile"]);
            $profile_qpart = "profile = '" . $_SESSION["profile"] . "'";
        } else {
            initialize_user_prefs($_SESSION["uid"]);
            $profile_qpart = "profile IS NULL";
        }

        /* if ($_SESSION["prefs_show_advanced"])
           $access_query = "true";
           else
           $access_query = "(access_level = 0 AND section_id != 3)"; */

        $access_query = 'true';

        $result = \SmallSmallRSS\Database::query(
            "SELECT DISTINCT
            ttrss_user_prefs.pref_name,value,type_name,
            ttrss_prefs_sections.order_id,
            def_value,section_id
            FROM ttrss_prefs,ttrss_prefs_types,ttrss_prefs_sections,ttrss_user_prefs
            WHERE type_id = ttrss_prefs_types.id AND
                $profile_qpart AND
                section_id = ttrss_prefs_sections.id AND
                ttrss_user_prefs.pref_name = ttrss_prefs.pref_name AND
                $access_query AND
                owner_uid = ".$_SESSION["uid"]."
            ORDER BY ttrss_prefs_sections.order_id,pref_name"
        );

        $lnum = 0;

        $active_section = "";

        $listed_boolean_prefs = array();

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

            if (in_array($line["pref_name"], $prefs_blacklist)) {
                continue;
            }

            $type_name = $line["type_name"];
            $pref_name = $line["pref_name"];
            $section_name = $this->getSectionName($line["section_id"]);
            $value = $line["value"];

            $short_desc = $this->getShortDesc($pref_name);
            $help_text = $this->getHelpText($pref_name);

            if (!$short_desc) {
                continue;
            }

            if (!empty($_SESSION["profile"]) && in_array(
                $line["pref_name"],
                $profile_blacklist
            )) {
                continue;
            }

            if ($active_section != $line["section_id"]) {

                if ($active_section != "") {
                    print "</table>";
                }

                print "<table width=\"100%\" class=\"prefPrefsList\">";

                $active_section = $line["section_id"];

                print "<tr><td colspan=\"3\"><h3>".$section_name."</h3></td></tr>";

                $lnum = 0;
            }

            print "<tr>";

            print "<td width=\"40%\" class=\"prefName\" id=\"$pref_name\">";
            print "<label for='CB_$pref_name'>";
            print $short_desc;
            print "</label>";

            if ($help_text) {
                print "<div class=\"prefHelp\">".__($help_text)."</div>";
            }

            print "</td>";

            print "<td class=\"prefValue\">";

            if ($pref_name == "USER_LANGUAGE") {
                $form_element_renderer->renderSelect(
                    $pref_name,
                    $value,
                    get_translations(),
                    "style='width: 220px; margin : 0px' dojoType='dijit.form.Select'"
                );

            } elseif ($pref_name == "USER_TIMEZONE") {

                $timezones = explode("\n", file_get_contents("lib/timezones.txt"));
                $timezone_array = array_combine($timezones, $timezones);
                $form_element_renderer->renderSelect(
                    $pref_name,
                    $value,
                    $timezone_array,
                    'dojoType="dijit.form.FilteringSelect"'
                );
            } elseif ($pref_name == "USER_STYLESHEET") {

                print "<button dojoType=\"dijit.form.Button\"
                    onclick=\"customizeCSS()\">" . __('Customize') . "</button>";

            } elseif ($pref_name == "USER_CSS_THEME") {
                $themes = array();
                foreach (glob("themes/*.css") as $theme) {
                    $themes[$theme] = $theme;
                }
                $form_element_renderer->renderSelect(
                    $pref_name,
                    $value,
                    $themes,
                    'dojoType="dijit.form.Select"'
                );


            } elseif ($pref_name == "DEFAULT_UPDATE_INTERVAL") {
                $form_element_renderer->renderSelect(
                    $pref_name,
                    $value,
                    \SmallSmallRSS\Constants::update_intervals_nodefault(),
                    'dojoType="dijit.form.Select"'
                );

            } elseif ($type_name == "bool") {

                array_push($listed_boolean_prefs, $pref_name);

                $checked = ($value == "true") ? "checked=\"checked\"" : "";

                if ($pref_name == "PURGE_UNREAD_ARTICLES" && \SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE') != 0) {
                    $disabled = "disabled=\"1\"";
                    $checked = "checked=\"checked\"";
                } else {
                    $disabled = "";
                }

                print "<input type='checkbox' name='$pref_name' $checked $disabled
                    dojoType='dijit.form.CheckBox' id='CB_$pref_name' value='1'>";

            } elseif (array_search($pref_name, array('FRESH_ARTICLE_MAX_AGE',
                                                      'PURGE_OLD_DAYS', 'LONG_DATE_FORMAT', 'SHORT_DATE_FORMAT')) !== false) {

                $regexp = ($type_name == 'integer') ? 'regexp="^\d*$"' : '';

                if ($pref_name == "PURGE_OLD_DAYS" && \SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE') != 0) {
                    $disabled = "disabled=\"1\"";
                    $value = \SmallSmallRSS\Config::get('FORCE_ARTICLE_PURGE');
                } else {
                    $disabled = "";
                }

                print "<input dojoType=\"dijit.form.ValidationTextBox\"
                    required=\"1\" $regexp $disabled
                    name=\"$pref_name\" value=\"$value\">";

            } elseif ($pref_name == "SSL_CERT_SERIAL") {

                print "<input dojoType=\"dijit.form.ValidationTextBox\"
                    id=\"SSL_CERT_SERIAL\" readonly=\"1\"
                    name=\"$pref_name\" value=\"$value\">";

                $cert_serial = htmlspecialchars(get_ssl_certificate_id());
                $has_serial = ($cert_serial) ? "false" : "true";

                print " <button dojoType=\"dijit.form.Button\" disabled=\"$has_serial\"
                    onclick=\"insertSSLserial('$cert_serial')\">" .
                    __('Register') . "</button>";

                print " <button dojoType=\"dijit.form.Button\"
                    onclick=\"insertSSLserial('')\">" .
                    __('Clear') . "</button>";

            } elseif ($pref_name == 'DIGEST_PREFERRED_TIME') {
                print "<input dojoType=\"dijit.form.ValidationTextBox\"
                    id=\"$pref_name\" regexp=\"[012]?\d:\d\d\" placeHolder=\"12:00\"
                    name=\"$pref_name\" value=\"$value\"><div class=\"insensitive\">".
                    T_sprintf("Current server time: %s (UTC)", date("H:i")) . "</div>";
            } else {
                $regexp = ($type_name == 'integer') ? 'regexp="^\d*$"' : '';

                print "<input dojoType=\"dijit.form.ValidationTextBox\"
                    $regexp
                    name=\"$pref_name\" value=\"$value\">";
            }

            print "</td>";

            print "</tr>";

            $lnum++;
        }

        print "</table>";

        $listed_boolean_prefs = htmlspecialchars(join(",", $listed_boolean_prefs));

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"boolean_prefs\" value=\"$listed_boolean_prefs\">";

        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::PREFS_TAB_SECTION,
            "prefPrefsPrefsInside"
        );

        print '</div>'; # inside pane
        print '<div dojoType="dijit.layout.ContentPane" region="bottom">';

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"pref-prefs\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"saveconfig\">";

        print "<div dojoType=\"dijit.form.ComboButton\" type=\"submit\">
            <span>".__('Save configuration')."</span>
            <div dojoType=\"dijit.DropDownMenu\">
                <div dojoType=\"dijit.MenuItem\"
                    onclick=\"dijit.byId('changeSettingsForm').onSubmit(null, true)\">".
            __("Save and exit preferences")."</div>
            </div>
            </div>";

        print "<button dojoType=\"dijit.form.Button\" onclick=\"return editProfiles()\">".
            __('Manage profiles')."</button> ";

        print "<button dojoType=\"dijit.form.Button\" onclick=\"return validatePrefsReset()\">".
            __('Reset to defaults')."</button>";

        print "&nbsp;";

        /* $checked = $_SESSION["prefs_show_advanced"] ? "checked='1'" : "";

           print "<input onclick='toggleAdvancedPrefs()'
           id='prefs_show_advanced'
           dojoType=\"dijit.form.CheckBox\"
           $checked
           type=\"checkbox\"></input>
           <label for='prefs_show_advanced'>" .
           __("Show additional preferences") . "</label>"; */

        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::PREFS_TAB_SECTION,
            "prefPrefsPrefsOutside"
        );

        print "</form>";
        print '</div>'; # inner pane
        print '</div>'; # border container

        print "</div>"; #pane

        print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Plugins')."\">";

        print "<p>" . __("You will need to reload Tiny Tiny RSS for plugin changes to take effect.") . "</p>";

        \SmallSmallRSS\Renderers\Messages::renderNotice(
            __("Download more plugins at tt-rss.org <a class=\"visibleLink\" target=\"_blank\" href=\"http://tt-rss.org/forum/viewforum.php?f=22\">forums</a> or <a target=\"_blank\" class=\"visibleLink\" href=\"http://tt-rss.org/wiki/Plugins\">wiki</a>.")
        );

        print "<form dojoType=\"dijit.form.Form\" id=\"changePluginsForm\">";

        print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
        evt.preventDefault();
        if (this.validate()) {
            notify_progress('Saving data...', true);

            new Ajax.Request('backend.php', {
                parameters: dojo.objectToQuery(this.getValues()),
                onComplete: function(transport) {
                    notify('');
                    if (confirm(__('Selected plugins have been enabled. Reload?'))) {
                        window.location.reload();
                    }
            } });

        }
        </script>";

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"pref-prefs\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"setplugins\">";

        print "<table width='100%' class='prefPluginsList'>";

        print "<tr><td colspan='4'><h3>".__("System plugins")."</h3></td></tr>";

        print "<tr class=\"title\">
                <td width=\"5%\">&nbsp;</td>
                <td width='10%'>".__('Plugin')."</td>
                <td width=''>".__('Description')."</td>
                <td width='5%'>".__('Version')."</td>
                <td width='10%'>".__('Author')."</td></tr>";

        $system_enabled = array_map('trim', explode(",", \SmallSmallRSS\Config::get('PLUGINS')));
        $user_enabled = array_map('trim', explode(",", \SmallSmallRSS\DBPrefs::read("_ENABLED_PLUGINS")));

        $tmppluginhost = new \SmallSmallRSS\PluginHost();
        $tmppluginhost->load_all($tmppluginhost::KIND_ALL, $_SESSION["uid"]);
        $tmppluginhost->load_data(true);

        foreach ($tmppluginhost->get_plugins() as $name => $plugin) {
            $about = $plugin->about();
            if (!empty($about[3]) && strpos($name, "example") === false) {
                if (in_array($name, $system_enabled)) {
                    $checked = "checked='1'";
                } else {
                    $checked = "";
                }

                print "<tr>";

                print "<td align='center'><input disabled='1'
                        dojoType=\"dijit.form.CheckBox\" $checked
                        type=\"checkbox\"></td>";

                print "<td>$name</td>";
                print "<td>" . htmlspecialchars($about[1]);
                if (@$about[4]) {
                    print " &mdash; <a target=\"_blank\" class=\"visibleLink\"
                        href=\"".htmlspecialchars($about[4])."\">".__("more info")."</a>";
                }
                print "</td>";
                print "<td>" . htmlspecialchars(sprintf("%.2f", $about[0])) . "</td>";
                print "<td>" . htmlspecialchars($about[2]) . "</td>";

                if (count($tmppluginhost->get_all($plugin)) > 0) {
                    if (in_array($name, $system_enabled)) {
                        print "<td><a href='#' onclick=\"clearPluginData('$name')\"
                            class='visibleLink'>".__("Clear data")."</a></td>";
                    }
                }

                print "</tr>";

            }
        }

        print "<tr><td colspan='4'><h3>".__("User plugins")."</h3></td></tr>";

        print "<tr class=\"title\">
                <td width=\"5%\">&nbsp;</td>
                <td width='10%'>".__('Plugin')."</td>
                <td width=''>".__('Description')."</td>
                <td width='5%'>".__('Version')."</td>
                <td width='10%'>".__('Author')."</td></tr>";


        foreach ($tmppluginhost->get_plugins() as $name => $plugin) {
            $about = $plugin->about();

            if (empty($about[3]) && strpos($name, "example") === false) {

                if (in_array($name, $system_enabled)) {
                    $checked = "checked='1'";
                    $disabled = "disabled='1'";
                    $rowclass = '';
                } elseif (in_array($name, $user_enabled)) {
                    $checked = "checked='1'";
                    $disabled = "";
                    $rowclass = "Selected";
                } else {
                    $checked = "";
                    $disabled = "";
                    $rowclass = '';
                }

                print "<tr class='$rowclass'>";

                print "<td align='center'>";
                print "<input id='FPCHK-$name' name='plugins[]' value='$name' onclick='toggleSelectRow2(this);'
                       dojoType=\"dijit.form.CheckBox\" $checked $disabled
                       type=\"checkbox\"></td>";

                print "<td><label for='FPCHK-$name'>$name</label></td>";
                print "<td><label for='FPCHK-$name'>" . htmlspecialchars($about[1]) . "</label>";
                if (@$about[4]) {
                    print " &mdash; <a target=\"_blank\" class=\"visibleLink\"
                        href=\"".htmlspecialchars($about[4])."\">".__("more info")."</a>";
                }
                print "</td>";

                print "<td>" . htmlspecialchars(sprintf("%.2f", $about[0])) . "</td>";
                print "<td>" . htmlspecialchars($about[2]) . "</td>";

                if (count($tmppluginhost->get_all($plugin)) > 0) {
                    if (in_array($name, $system_enabled) || in_array($name, $user_enabled)) {
                        print "<td><a href='#' onclick=\"clearPluginData('$name')\" class='visibleLink'>";
                        print __("Clear data")."</a></td>";
                    }
                }

                print "</tr>";



            }

        }

        print "</table>";

        print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
            __("Enable selected plugins")."</button></p>";

        print "</form>";

        print "</div>"; #pane

        \SmallSmallRSS\PluginHost::getInstance()->runHooks(
            \SmallSmallRSS\Hooks::PREFS_TAB,
            "prefPrefs"
        );

        print "</div>"; #container
    }

    public function toggleAdvanced()
    {
        $_SESSION["prefs_show_advanced"] = !$_SESSION["prefs_show_advanced"];
    }

    public function otpqrcode()
    {
        require_once "lib/otphp/vendor/base32.php";
        require_once "lib/otphp/lib/otp.php";
        require_once "lib/otphp/lib/totp.php";
        require_once "lib/phpqrcode/phpqrcode.php";

        $result = \SmallSmallRSS\Database::query(
            "SELECT login,salt,otp_enabled
            FROM ttrss_users
            WHERE id = ".$_SESSION["uid"]
        );

        $base32 = new Base32();

        $login = \SmallSmallRSS\Database::fetch_result($result, 0, "login");
        $otp_enabled = sql_bool_to_bool(\SmallSmallRSS\Database::fetch_result($result, 0, "otp_enabled"));

        if (!$otp_enabled) {
            $secret = $base32->encode(sha1(\SmallSmallRSS\Database::fetch_result($result, 0, "salt")));
            $topt = new \OTPHP\TOTP($secret);
            print QRcode::png($topt->provisioning_uri($login));
        }
    }

    public function otpenable()
    {
        require_once "lib/otphp/vendor/base32.php";
        require_once "lib/otphp/lib/otp.php";
        require_once "lib/otphp/lib/totp.php";

        $password = $_REQUEST["password"];
        $otp = $_REQUEST["otp"];

        $authenticator = \SmallSmallRSS\PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);

        if ($authenticator->check_password($_SESSION["uid"], $password)) {

            $result = \SmallSmallRSS\Database::query(
                "SELECT salt
                FROM ttrss_users
                WHERE id = ".$_SESSION["uid"]
            );

            $base32 = new Base32();

            $secret = $base32->encode(sha1(\SmallSmallRSS\Database::fetch_result($result, 0, "salt")));
            $topt = new \OTPHP\TOTP($secret);

            $otp_check = $topt->now();

            if ($otp == $otp_check) {
                \SmallSmallRSS\Database::query(
                    "UPDATE ttrss_users SET otp_enabled = true WHERE
                    id = " . $_SESSION["uid"]
                );

                print "OK";
            } else {
                print "ERROR:".__("Incorrect one time password");
            }
        } else {
            print "ERROR:".__("Incorrect password");
        }

    }

    public function otpdisable()
    {
        $password = \SmallSmallRSS\Database::escape_string($_REQUEST["password"]);

        $authenticator = \SmallSmallRSS\PluginHost::getInstance()->get_plugin($_SESSION["auth_module"]);

        if ($authenticator->check_password($_SESSION["uid"], $password)) {

            \SmallSmallRSS\Database::query(
                "UPDATE ttrss_users SET otp_enabled = false WHERE
                id = " . $_SESSION["uid"]
            );

            print "OK";
        } else {
            print "ERROR: ".__("Incorrect password");
        }

    }

    public function setplugins()
    {
        if (is_array($_REQUEST["plugins"])) {
            $plugins = join(",", $_REQUEST["plugins"]);
        } else {
            $plugins = "";
        }

        \SmallSmallRSS\DBPrefs::write("_ENABLED_PLUGINS", $plugins);
    }

    public function clearplugindata()
    {
        $name = \SmallSmallRSS\Database::escape_string($_REQUEST["name"]);

        \SmallSmallRSS\PluginHost::getInstance()->clear_data(\SmallSmallRSS\PluginHost::getInstance()->get_plugin($name));
    }

    public function customizeCSS()
    {
        $value = \SmallSmallRSS\DBPrefs::read("USER_STYLESHEET");

        $value = str_replace("<br/>", "\n", $value);

        \SmallSmallRSS\Renderers\Messages::renderNotice(
            T_sprintf(
                "You can override colors, fonts and layout of your currently selected theme with custom CSS declarations here. <a target=\"_blank\" class=\"visibleLink\" href=\"%s\">This file</a> can be used as a baseline.",
                "css/tt-rss.css"
            )
        );

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"rpc\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"setpref\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"key\" value=\"USER_STYLESHEET\">";

        print "<table width='100%'><tr><td>";
        print "<textarea dojoType=\"dijit.form.SimpleTextarea\"
            style='font-size: 12px; width: 100%; height: 200px;'
            placeHolder='body#ttrssMain { font-size: 14px; };'
            name='value'>$value</textarea>";
        print "</td></tr></table>";

        print "<div class='dlgButtons'>";
        print "<button dojoType=\"dijit.form.Button\"
            onclick=\"dijit.byId('cssEditDlg').execute()\">".__('Save')."</button> ";
        print "<button dojoType=\"dijit.form.Button\"
            onclick=\"dijit.byId('cssEditDlg').hide()\">".__('Cancel')."</button>";
        print "</div>";

    }

    public function editPrefProfiles()
    {
        print "<div dojoType=\"dijit.Toolbar\">";

        print "<div dojoType=\"dijit.form.DropDownButton\">".
            "<span>" . __('Select')."</span>";
        print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
        print "<div onclick=\"selectTableRows('prefFeedProfileList', 'all')\"
            dojoType=\"dijit.MenuItem\">".__('All')."</div>";
        print "<div onclick=\"selectTableRows('prefFeedProfileList', 'none')\"
            dojoType=\"dijit.MenuItem\">".__('None')."</div>";
        print "</div></div>";

        print "<div style=\"float : right\">";

        print "<input name=\"newprofile\" dojoType=\"dijit.form.ValidationTextBox\"
                required=\"1\">
            <button dojoType=\"dijit.form.Button\"
            onclick=\"dijit.byId('profileEditDlg').addProfile()\">".
            __('Create profile')."</button></div>";

        print "</div>";

        $result = \SmallSmallRSS\Database::query(
            "SELECT title,id FROM ttrss_settings_profiles
            WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title"
        );

        print "<div class=\"prefProfileHolder\">";

        print "<form id=\"profile_edit_form\" onsubmit=\"return false\">";

        print "<table width=\"100%\" class=\"prefFeedProfileList\"
            cellspacing=\"0\" id=\"prefFeedProfileList\">";

        print "<tr class=\"placeholder\" id=\"FCATR-0\">"; #odd

        print "<td width='5%' align='center'><input
            id='FCATC-0'
            onclick='toggleSelectRow2(this);'
            dojoType=\"dijit.form.CheckBox\"
            type=\"checkbox\"></td>";

        if (!$_SESSION["profile"]) {
            $is_active = __("(active)");
        } else {
            $is_active = "";
        }

        print "<td><span>" .
            __("Default profile") . " $is_active</span></td>";

        print "</tr>";

        $lnum = 1;

        while ($line = \SmallSmallRSS\Database::fetch_assoc($result)) {

            $profile_id = $line["id"];
            $this_row_id = "id=\"FCATR-$profile_id\"";

            print "<tr class=\"placeholder\" $this_row_id>";

            $edit_title = htmlspecialchars($line["title"]);

            print "<td width='5%' align='center'><input
                onclick='toggleSelectRow2(this);'
                id='FCATC-$profile_id'
                dojoType=\"dijit.form.CheckBox\"
                type=\"checkbox\"></td>";

            if ($_SESSION["profile"] == $line["id"]) {
                $is_active = __("(active)");
            } else {
                $is_active = "";
            }

            print "<td><span dojoType=\"dijit.InlineEditBox\"
                width=\"300px\" autoSave=\"false\"
                profile-id=\"$profile_id\">" . $edit_title .
                "<script type=\"dojo/method\" event=\"onChange\" args=\"item\">
                    var elem = this;
                    dojo.xhrPost({
                        url: 'backend.php',
                        content: {op: 'rpc', method: 'saveprofile',
                            value: this.value,
                            id: this.srcNodeRef.getAttribute('profile-id')},
                            load: function(response) {
                                elem.attr('value', response);
                        }
                    });
                </script>
            </span> $is_active</td>";

            print "</tr>";

            ++$lnum;
        }

        print "</table>";
        print "</form>";
        print "</div>";

        print "<div class='dlgButtons'>
            <div style='float : left'>
            <button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').removeSelected()\">".
            __('Remove selected profiles')."</button>
            <button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').activateProfile()\">".
            __('Activate profile')."</button>
            </div>";

        print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('profileEditDlg').hide()\">".
            __('Close this window')."</button>";
        print "</div>";

    }

    private function getShortDesc($pref_name)
    {
        if (isset($this->pref_help[$pref_name][0])) {
            return $this->pref_help[$pref_name][0];
        }
        return "";
    }

    private function getHelpText($pref_name)
    {
        if (isset($this->pref_help[$pref_name][1])) {
            return $this->pref_help[$pref_name][1];
        }
        return "";
    }

    private function getSectionName($id)
    {
        if (isset($this->pref_sections[$id])) {
            return $this->pref_sections[$id];
        }

        return "";
    }
}
