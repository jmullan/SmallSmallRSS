<?php
namespace SmallSmallRSS;

class Hooks
{
    const RENDER_ARTICLE_BUTTON = 1;
    const FILTER_INCOMING_ARTICLE = 2;
    const RENDER_PREFS_TAB = 3;
    const RENDER_PREFS_TAB_SECTION = 4;
    const RENDER_PREFS_TABS = 5;
    const FEED_PARSED = 6;
    const UPDATE_TASK = 7;
    const AUTH_USER = 8;
    const FILTER_HOTKEY_MAP = 9;
    const FILTER_ARTICLE = 10;
    const RENDER_ARTICLE_CDM = 11;
    const FEED_FETCHED = 12;
    const SANITIZE = 13;
    const FILTER_ARTICLE_API = 14;
    const RENDER_TOOLBAR_BUTTON = 15;
    const RENDER_ACTION_ITEM = 16;
    const RENDER_HEADLINE_TOOLBAR_BUTTON = 17;
    const FILTER_HOTKEY_INFO = 18;
    const RENDER_ARTICLE_LEFT_BUTTON = 19;
    const PREFS_EDIT_FEED = 20;
    const PREFS_SAVE_FEED = 21;
    const FETCH_FEED = 22;
    const GUID_FILTER = 23;

    public static function getHookMethod($hook)
    {
        $mappings = array(
            self::FILTER_INCOMING_ARTICLE => 'hookFilterIncomingArticle',
            self::FILTER_ARTICLE => 'hookFilterArticle',
            self::RENDER_PREFS_TAB => 'hookRenderPreferencesTab',
            self::UPDATE_TASK => 'hookUpdateTask',
            self::FEED_PARSED => 'hookFeedParsed',
            self::RENDER_PREFS_TAB_SECTION => 'hookRenderPreferencesTabSection',
            self::PREFS_EDIT_FEED => 'hookPreferencesEditFeed',
            self::PREFS_SAVE_FEED => 'hookPrefsSaveFeed',
            self::RENDER_PREFS_TAB => 'hookRenderPreferencesTab',
            self::FILTER_ARTICLE_API => 'hookFilterArticleApi',
            self::RENDER_ARTICLE_CDM => 'hookRenderArticleCDM',
            self::RENDER_ARTICLE_LEFT_BUTTON => 'hookRenderArticleLeftButton',
            self::RENDER_ARTICLE_BUTTON => 'hookRenderArticleButton',
            self::RENDER_TOOLBAR_BUTTON => 'hookRenderToolbarButton',
            self::RENDER_ACTION_ITEM => 'hookRenderActionItem',
            self::RENDER_HEADLINE_TOOLBAR_BUTTON => 'hookRenderHeadlineToolbarButton',
        );
        if (isset($mappings[$hook])) {
            return $mappings[$hook];
        }
        return false;
    }
}
