<?php
namespace SmallSmallRSS\Handlers;

class Backend extends Handler
{
    public function loading()
    {
        header('Content-type: text/html');
        echo __('Loading, please wait...');
        echo ' ';
        echo '<img src="images/indicator_tiny.gif">';
    }

    public function digestTest()
    {
        header('Content-type: text/html');
        $rv = \SmallSmallRSS\Digest::prepare_headlines($_SESSION['uid'], 1, 1000);
        $rv[3] = '<pre>' . $rv[3] . '</pre>';
        print_r($rv);
    }

    private function displayMainHelp()
    {
        $info = \SmallSmallRSS\Hotkeys::info();
        $imap = \SmallSmallRSS\Hotkeys::map();
        $omap = array();
        foreach ($imap[1] as $sequence => $action) {
            if (!isset($omap[$action])) {
                $omap[$action] = array();
            }
            $omap[$action][] = $sequence;
        }
        \SmallSmallRSS\Renderers\Messages::renderNotice(
            '<a target="_blank" href="http://tt-rss.org/wiki/InterfaceTips">'
            . __('Other interface tips are available in the Tiny Tiny RSS wiki.')
            . '</a>'
        );
        echo '<ul class="helpKbLis" id="helpKbList">';
        echo '<h2>' . __('Keyboard Shortcuts') . '</h2>';
        foreach ($info as $section => $hotkeys) {
            echo '<li><h3>' . $section . '</h3></li>';
            foreach ($hotkeys as $action => $description) {
                if (is_array($omap[$action])) {
                    foreach ($omap[$action] as $sequence) {
                        if (strpos($sequence, '|') !== false) {
                            $sequence = substr($sequence, strpos($sequence, '|') + 1, strlen($sequence));
                        } else {
                            $keys = explode(' ', $sequence);
                            for ($i = 0; $i < count($keys); $i++) {
                                if (strlen($keys[$i]) > 1) {
                                    $tmp = '';
                                    foreach (str_split($keys[$i]) as $c) {
                                        switch ($c) {
                                            case '*':
                                                $tmp .= __('Shift') . '+';
                                                break;
                                            case '^':
                                                $tmp .= __('Ctrl') . '+';
                                                break;
                                            default:
                                                $tmp .= $c;
                                        }
                                    }
                                    $keys[$i] = $tmp;
                                }
                            }
                            $sequence = join(' ', $keys);
                        }

                        echo '<li>';
                        echo '<span class="hksequence">';
                        echo $sequence;
                        echo '</span>';
                        echo $description;
                        echo '</li>';
                    }
                }
            }
        }
        echo '</ul>';
    }

    public function help()
    {
        $topic = basename($_REQUEST['topic']);
        switch ($topic) {
            case 'main':
                $this->displayMainHelp();
                break;
            case 'prefs':
                //$this->display_prefs_help();
                break;
            default:
                echo '<p>'.__('Help topic not found.').'</p>';
        }
        echo "<div align='center'>";
        echo '<button data-dojo-type="dijit.form.Button"';
        echo " onclick=\"return dijit.byId('helpDlg').hide()\">";
        echo __('Close this window');
        echo '</button>';
        echo '</div>';
    }
}
