<?php
namespace SmallSmallRSS\Renderers;

class FormElements extends \SmallSmallRSS\Renderers\Base
{
    public function renderSelect($id, $default, $values, $attributes = "")
    {
        print "<select name=\"$id\" id='$id' $attributes>";
        foreach ($values as $k => $v) {
            if ($k == $default) {
                $sel = ' selected="selected"';
            } else {
                $sel = '';
            }
            $k = trim($k);
            print "<option value=\"$k\"$sel>";
            print $v;
            print '</option>';
        }
        print '</select>';
    }

    public function renderRadio($id, $default, $true_is, $values, $attributes = "")
    {
        foreach ($values as $v) {
            if ($v == $default) {
                $sel = ' checked="checked"';
            } else {
                $sel = '';
            }
            if ($v == $true_is) {
                $sel .= ' value="1"';
            } else {
                $sel .= ' value="0"';
            }
            print "<input type=\"radio\" class=\"noborder\" dojoType=\"dijit.form.RadioButton\"";
            print " $sel $attributes name=\"$id\" id=\"$id\" />";
            print "<label for=\"$id\">$v</label>";
            print "\n";
        }
    }
}
