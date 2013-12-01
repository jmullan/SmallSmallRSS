<?php
namespace SmallSmallRSS\Renderers;

class Messages extends \SmallSmallRSS\Renderers\Base
{
    public static function renderWarning($msg, $id = '')
    {
        self::renderMessage('warning', 'images/sign_excl.svg', $msg, $id);
    }
    public static function renderNotice($msg, $id = '')
    {
        self::renderMessage('notice', 'images/sign_info.svg', $msg, $id);
    }
    public static function renderError($msg, $id = '')
    {
        self::renderMessage('error', 'images/sign_excl.svg', $msg, $id);
    }
    public static function renderMessage($class, $image, $msg, $id = '')
    {
            echo '<div class="' . $class . '"';
            if (strlen($id)) {
                echo ' id="' . $id . '"';
            }
            echo '>"';
            echo '<span>';
            echo '<img src="' . $image . '" alt="">';
            echo '</span>';
            echo '<span>';
            echo $msg;
            echo '</span>';
            echo '</div>';
    }
}
