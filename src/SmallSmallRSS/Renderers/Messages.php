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
        if (strlen($id)) {
            print '<div class="' . $class . '" id="' . $id . '"><span><img src="'
                . $image . '"></span><span>' . $msg . '</span></div>';
        } else {
            print '<div class="' . $class . '"><span><img src="' . $image . '"></span><span>' . $msg . '</span></div>';
        }
    }
}
