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
        \SmallSmallRSS\Hooks::RENDER_ARTICLE_BUTTON
    );

    public function getJavascript()
    {
        return file_get_contents(__DIR__ . "/note.js");
    }


    public function hookRenderArticleButton($line)
    {
        echo "<img src=\"plugins/note/note.png\"";
        echo " style=\"cursor : pointer\" style=\"cursor: pointer\"";
        echo " onclick=\"editArticleNote(";
        echo $line["id"];
        echo ")\"";
        echo " class='tagsPic' title='";
        echo __('Edit article note');
        echo "'>";
    }

    public function edit()
    {
        $param = \SmallSmallRSS\Database::escape_string($_REQUEST['param']);
        $note = \SmallSmallRSS\UserEntries::getNote($param, $_SESSION['uid']);
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"id\" value=\"$param\">";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" ";
        echo " style=\"display: none\" name=\"op\" value=\"pluginhandler\">";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"setNote\">";
        echo "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"plugin\" value=\"note\">";

        echo "<table width='100%'><tr><td>";
        echo "<textarea data-dojo-type=\"dijit.form.SimpleTextarea\"";
        echo " style=\"font-size: 12px; width: 100%; height: 100px;\"";
        echo " placeHolder=\"body#ttrssMain { font-size: 14px; };\"";
        echo " name=\"note\">";
        echo $note;
        echo "</textarea>";
        echo "</td>";
        echo "</tr>";
        echo "</table>";

        echo "<div class='dlgButtons'>";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('editNoteDlg').execute()\">";
        echo __('Save');
        echo "</button> ";
        echo "<button data-dojo-type=\"dijit.form.Button\" onclick=\"dijit.byId('editNoteDlg').hide()\">";
        echo __('Cancel');
        echo "</button>";
        echo "</div>";

    }

    public function setNote()
    {
        \SmallSmallRSS\UserEntries::setNote($_REQUEST['id'], $_SESSION['uid'], $_REQUEST['note']);
        $note = \SmallSmallRSS\UserEntries::getNote($_REQUEST['id'], $_SESSION['uid']);
        $formatted_note = format_article_note($_REQUEST['id'], $note);
        echo json_encode(array("note" => $formatted_note, "raw_length" => mb_strlen($note)));
    }
}
