<?php

class NSFW extends \SmallSmallRSS\Plugin
{
    const API_VERSION = 2;
    const VERSION = 1.0;
    const NAME = 'Hide NSFW Articles';
    const DESCRIPTION = 'Hide article content based on tags';
    const AUTHOR = 'fox';
    const IS_SYSTEM = false;

    public static $provides = array(
        \SmallSmallRSS\Hooks::FILTER_ARTICLE,
        \SmallSmallRSS\Hooks::RENDER_ARTICLE_CDM,
        \SmallSmallRSS\Hooks::RENDER_PREFS_TAB
    );

    public function getJavascript()
    {
        return file_get_contents(dirname(__FILE__) . "/init.js");
    }

    public function hookFilterArticle($article)
    {
        $tags = array_map("trim", explode(",", $this->host->get($this, "tags")));
        $a_tags = array_map("trim", explode(",", $article["tag_cache"]));
        if (count(array_intersect($tags, $a_tags)) > 0) {
            $article["content"] = (
                "<div class='nswf wrapper'><button onclick=\"nsfwShow(this)\">"
                . __("Not work safe (click to toggle)")
                . "</button>"
                . "<div class='nswf content' style='display: none'>"
                . $article["content"]
                . "</div></div>"
            );
        }
        return $article;
    }

    public function hookRenderArticleCDM($article)
    {
        $tags = array_map("trim", explode(",", $this->host->get($this, "tags")));
        $a_tags = array_map("trim", explode(",", $article["tag_cache"]));

        if (count(array_intersect($tags, $a_tags)) > 0) {
            $article["content"] = (
                "<div class='nswf wrapper'><button onclick=\"nsfwShow(this)\">"
                . __("Not work safe (click to toggle)")
                . "</button>"
                . "<div class='nswf content' style='display: none'>"
                .$article["content"]."</div></div>"
            );
        }
        return $article;
    }

    public function hookRenderPreferencesTab($args)
    {
        if ($args != "prefPrefs") {
            return;
        }
        print "<div data-dojo-type=\"dijit.layout.AccordionPane\" title=\"".__("NSFW Plugin")."\">";
        print "<br/>";
        $tags = $this->host->get($this, "tags");
        print "<form data-dojo-type=\"dijit.form.Form\">";
        print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
                 evt.preventDefault();
                 if (this.validate()) {
                   new Ajax.Request('backend.php', {
                     parameters: dojo.objectToQuery(this.getValues()),
                     onComplete: function(transport) {
                       notify_info(transport.responseText);
                     }
                   });
                 }
               </script>";
        print "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"op\" value=\"pluginhandler\">";
        print "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"method\" value=\"save\">";
        print "<input data-dojo-type=\"dijit.form.TextBox\" style=\"display: none\" name=\"plugin\" value=\"nsfw\">";
        print "<table width=\"100%\" class=\"prefPrefsList\">";
        print "<tr><td width=\"40%\">".__("Tags to consider NSFW (comma-separated)")."</td>";
        print "<td class=\"prefValue\">";
        print "<input data-dojo-type=\"dijit.form.ValidationTextBox\" required=\"1\" name=\"tags\" value=\"$tags\">";
        print "</td>";
        print "</tr>";
        print "</table>";
        print "<p><button data-dojo-type=\"dijit.form.Button\" type=\"submit\">";
        print __("Save");
        print "</button>";
        print "</form>";
        print "</div>"; #pane
    }

    public function save()
    {
        $tags = explode(",", \SmallSmallRSS\Database::escape_string($_POST["tags"]));
        $tags = array_map("trim", $tags);
        $tags = array_map("mb_strtolower", $tags);
        $tags = join(", ", $tags);
        $this->host->set($this, "tags", $tags);
        echo __("Configuration saved.");
    }
}
