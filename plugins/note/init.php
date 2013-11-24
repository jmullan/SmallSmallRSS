<?php

class Note extends \SmallSmallRSS\Plugin
{
    private $host;

    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Article Notes';
    const DESCRIPTION = 'Adds support for setting article notes';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::ARTICLE_BUTTON
    );

    public function getJavascript()
    {
        return file_get_contents(__DIR__ . "/note.js");
    }


    public function hookArticleButton($line)
    {
        return "<img src=\"plugins/note/note.png\"
            style=\"cursor : pointer\" style=\"cursor : pointer\"
            onclick=\"editArticleNote(".$line["id"].")\"
            class='tagsPic' title='".__('Edit article note')."'>";
    }

    public function edit()
    {
        $param = \SmallSmallRSS\Database::escape_string($_REQUEST['param']);

        $result = \SmallSmallRSS\Database::query(
            "SELECT note
             FROM ttrss_user_entries
             WHERE
                 ref_id = '$param'
                 AND owner_uid = " . $_SESSION['uid']
        );

        $note = \SmallSmallRSS\Database::fetch_result($result, 0, "note");

        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"id\" value=\"$param\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"pluginhandler\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"setNote\">";
        print "<input dojoType=\"dijit.form.TextBox\" style=\"display: none\" name=\"plugin\" value=\"note\">";

        print "<table width='100%'><tr><td>";
        print "<textarea dojoType=\"dijit.form.SimpleTextarea\"
            style='font-size: 12px; width: 100%; height: 100px;'
            placeHolder='body#ttrssMain { font-size: 14px; };'
            name='note'>$note</textarea>";
        print "</td></tr></table>";

        print "<div class='dlgButtons'>";
        print "<button dojoType=\"dijit.form.Button\"
            onclick=\"dijit.byId('editNoteDlg').execute()\">".__('Save')."</button> ";
        print "<button dojoType=\"dijit.form.Button\"
            onclick=\"dijit.byId('editNoteDlg').hide()\">".__('Cancel')."</button>";
        print "</div>";

    }

    public function setNote()
    {
        $id = \SmallSmallRSS\Database::escape_string($_REQUEST["id"]);
        $note = trim(strip_tags(\SmallSmallRSS\Database::escape_string($_REQUEST["note"])));

        \SmallSmallRSS\Database::query(
            "UPDATE ttrss_user_entries
             SET note = '$note'
             WHERE
                 ref_id = '$id'
                 AND owner_uid = " . $_SESSION["uid"]
        );
        $formatted_note = format_article_note($id, $note);
        print json_encode(array("note" => $formatted_note, "raw_length" => mb_strlen($note)));
    }
}
