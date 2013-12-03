<?php

namespace SmallSmallRSS;

class HotKeys
{
    public static function info()
    {
        $hotkeys = array(
            __('Navigation') => array(
                'next_feed' => __('Open next feed'),
                'prev_feed' => __('Open previous feed'),
                'next_article' => __('Open next article'),
                'prev_article' => __('Open previous article'),
                'next_article_noscroll' => __("Open next article (don't scroll long articles)"),
                'prev_article_noscroll' => __("Open previous article (don't scroll long articles)"),
                'next_article_noexpand' => __("Move to next article (don't expand or mark read)"),
                'prev_article_noexpand' => __("Move to previous article (don't expand or mark read)"),
                'search_dialog' => __('Show search dialog')),
            __('Article') => array(
                'toggle_mark' => __('Toggle starred'),
                'toggle_publ' => __('Toggle published'),
                'toggle_unread' => __('Toggle unread'),
                'edit_tags' => __('Edit tags'),
                'dismiss_selected' => __('Dismiss selected'),
                'dismiss_read' => __('Dismiss read'),
                'open_in_new_window' => __('Open in new window'),
                'catchup_below' => __('Mark below as read'),
                'catchup_above' => __('Mark above as read'),
                'article_scroll_down' => __('Scroll down'),
                'article_scroll_up' => __('Scroll up'),
                'select_article_cursor' => __('Select article under cursor'),
                'email_article' => __('Email article'),
                'close_article' => __('Close/collapse article'),
                'toggle_expand' => __('Toggle article expansion (combined mode)'),
                'toggle_widescreen' => __('Toggle widescreen mode'),
                'toggle_embed_original' => __('Toggle embed original')),
            __('Article selection') => array(
                'select_all' => __('Select all articles'),
                'select_unread' => __('Select unread'),
                'select_marked' => __('Select starred'),
                'select_published' => __('Select published'),
                'select_invert' => __('Invert selection'),
                'select_none' => __('Deselect everything')),
            __('Feed') => array(
                'feed_refresh' => __('Refresh current feed'),
                'feed_unhide_read' => __('Un/hide read feeds'),
                'feed_subscribe' => __('Subscribe to feed'),
                'feed_edit' => __('Edit feed'),
                'feed_catchup' => __('Mark as read'),
                'feed_reverse' => __('Reverse headlines'),
                'feed_debug_update' => __('Debug feed update'),
                'catchup_all' => __('Mark all feeds as read'),
                'cat_toggle_collapse' => __('Un/collapse current category'),
                'toggle_combined_mode' => __('Toggle combined mode'),
                'toggle_cdm_expanded' => __('Toggle auto expand in combined mode')),
            __('Go to') => array(
                'goto_all' => __('All articles'),
                'goto_fresh' => __('Fresh'),
                'goto_marked' => __('Starred'),
                'goto_published' => __('Published'),
                'goto_tagcloud' => __('Tag cloud'),
                'goto_prefs' => __('Preferences')),
            __('Other') => array(
                'create_label' => __('Create label'),
                'create_filter' => __('Create filter'),
                'collapse_sidebar' => __('Un/collapse sidebar'),
                'help_dialog' => __('Show help dialog'))
        );
        $hotkey_hooks = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
            \SmallSmallRSS\Hooks::FILTER_HOTKEY_INFO
        );
        foreach ($hotkey_hooks as $plugin) {
            $hotkeys = $plugin->hookFilterHotKeyInfo($hotkeys);
        }
        return $hotkeys;
    }
    public static function map()
    {
        $hotkeys = array(
            //            "navigation" => array(
            'k' => 'next_feed',
            'j' => 'prev_feed',
            'n' => 'next_article',
            'p' => 'prev_article',
            '(38)|up' => 'prev_article',
            '(40)|down' => 'next_article',
            //                "^(38)|Ctrl-up" => "prev_article_noscroll",
            //                "^(40)|Ctrl-down" => "next_article_noscroll",
            '(191)|/' => 'search_dialog',
            //            "article" => array(
            's' => 'toggle_mark',
            '*s' => 'toggle_publ',
            'u' => 'toggle_unread',
            '*t' => 'edit_tags',
            '*d' => 'dismiss_selected',
            '*x' => 'dismiss_read',
            'o' => 'open_in_new_window',
            'c p' => 'catchup_below',
            'c n' => 'catchup_above',
            '*n' => 'article_scroll_down',
            '*p' => 'article_scroll_up',
            '*(38)|Shift+up' => 'article_scroll_up',
            '*(40)|Shift+down' => 'article_scroll_down',
            'a *w' => 'toggle_widescreen',
            'a e' => 'toggle_embed_original',
            'e' => 'email_article',
            'a q' => 'close_article',
            //            "article_selection" => array(
            'a a' => 'select_all',
            'a u' => 'select_unread',
            'a *u' => 'select_marked',
            'a p' => 'select_published',
            'a i' => 'select_invert',
            'a n' => 'select_none',
            //            "feed" => array(
            'f r' => 'feed_refresh',
            'f a' => 'feed_unhide_read',
            'f s' => 'feed_subscribe',
            'f e' => 'feed_edit',
            'f q' => 'feed_catchup',
            'f x' => 'feed_reverse',
            'f *d' => 'feed_debug_update',
            'f *c' => 'toggle_combined_mode',
            'f c' => 'toggle_cdm_expanded',
            '*q' => 'catchup_all',
            'x' => 'cat_toggle_collapse',
            //            "goto" => array(
            'g a' => 'goto_all',
            'g f' => 'goto_fresh',
            'g s' => 'goto_marked',
            'g p' => 'goto_published',
            'g t' => 'goto_tagcloud',
            'g *p' => 'goto_prefs',
            //            "other" => array(
            '(9)|Tab' => 'select_article_cursor', // tab
            'c l' => 'create_label',
            'c f' => 'create_filter',
            'c s' => 'collapse_sidebar',
            '^(191)|Ctrl+/' => 'help_dialog',
        );

        if (\SmallSmallRSS\DBPrefs::read('COMBINED_DISPLAY_MODE')) {
            $hotkeys['^(38)|Ctrl-up'] = 'prev_article_noscroll';
            $hotkeys['^(40)|Ctrl-down'] = 'next_article_noscroll';
        }

        $hotkey_hooks = \SmallSmallRSS\PluginHost::getInstance()->get_hooks(
            \SmallSmallRSS\Hooks::HOTKEY_MAP
        );
        foreach ($hotkey_hooks as $plugin) {
            $hotkeys = $plugin->hookHotkeyMap($hotkeys);
        }
        $prefixes = array();
        foreach (array_keys($hotkeys) as $hotkey) {
            $pair = explode(' ', $hotkey, 2);
            if (count($pair) > 1 && !in_array($pair[0], $prefixes)) {
                array_push($prefixes, $pair[0]);
            }
        }
        return array($prefixes, $hotkeys);
    }
}