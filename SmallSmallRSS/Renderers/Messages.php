<?php
namespace SmallSmallRSS\Renderers;
class Messages extends \SmallSmallRSS\Renderers\Base {
    function render_warning($msg, $id) {
        $this->render_message('warning', 'images/sign_excl.svg', $msg, $id);
    }
    function render_notice($msg, $id) {
        $this->render_message('notice', 'images/sign_info.svg', $msg, $id);
    }
    function render_error($msg, $id) {
        $this->render_message('error', 'images/sign_excl.svg', $msg, $id);
    }
    function render_message($class, $image, $msg, $id) {
        if (strlen($id)) {
            print '<div class="' . $class . '" id="' . $id . '"><span><img src="' . $image . '"></span><span>' . $msg . '</span></div>';
        } else {
            print '<div class="' . $class . '"><span><img src="' . $image . '"></span><span>' . $msg . '</span></div>';
        }
    }
    public static function print_warning($msg, $id="") {
        $message = new self();
        $message->render_warning($msg, $id);
    }

    public static function print_notice($msg, $id="") {
        $message = new self();
        $message->render_notice($msg, $id);
    }

    public static function print_error($msg, $id="") {
        $message = new self();
        $message->render_error($msg, $id);
    }
}
