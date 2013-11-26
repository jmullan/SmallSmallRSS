<?php
namespace SmallSmallRSS\Renderers;

class JSTranslations
{
    public function render()
    {
        print 'var T_messages = new Object();
        function __(msg) {
            if (T_messages[msg]) {
                return T_messages[msg];
            } else {
                return msg;
            }
        }
        function ngettext(msg1, msg2, n) {
            return (parseInt(n) > 1) ? msg2 : msg1;
        }';
        $l10n = _get_reader();
        for ($i = 0; $i < $l10n->total; $i++) {
            $orig = $l10n->get_original_string($i);
            $translation = __($orig);
            self::T_js_decl($orig, $translation);
        }
    }

    public static function T_js_decl($s1, $s2)
    {
        if ($s1 && $s2) {
            $s1 = preg_replace("/\n/", "", $s1);
            $s2 = preg_replace("/\n/", "", $s2);
            $s1 = preg_replace("/\"/", "\\\"", $s1);
            $s2 = preg_replace("/\"/", "\\\"", $s2);
            echo "T_messages[\"$s1\"] = \"$s2\";\n";
        }
    }
}
