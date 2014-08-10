<?php
namespace SmallSmallRSS;

class Locale
{
    public static $translations = array(
        'auto' => 'Detect automatically',
        'ca_CA' => 'Català',
        'cs_CZ' => 'Česky',
        'en_US' => 'English',
        'es_ES' => 'Español',
        'de_DE' => 'Deutsch',
        'fr_FR' => 'Français',
        'hu_HU' => 'Magyar (Hungarian)',
        'it_IT' => 'Italiano',
        'ja_JP' => '日本語 (Japanese)',
        'ko_KR' => '한국어 (Korean)',
        'lv_LV' => 'Latviešu',
        'nb_NO' => 'Norwegian bokmål',
        'nl_NL' => 'Dutch',
        'pl_PL' => 'Polski',
        'ru_RU' => 'Русский',
        'pt_BR' => 'Portuguese/Brazil',
        'zh_CN' => 'Simplified Chinese',
        'sv_SE' => 'Svenska',
        'fi_FI' => 'Suomi'
    );

    public static function startupGettext($owner_uid)
    {
        # Get locale from Accept-Language header
        $locale = \AcceptToGettext\Scorer::al2gt(
            array_keys(\SmallSmallRSS\Locale::$translations),
            'text/html'
        );
        $forced_locale = \SmallSmallRSS\Config::get('FORCE_LOCALE');
        if ($forced_locale and $forced_locale != 'auto') {
            $locale = $forced_locale;
        }
        if (!empty($owner_uid)
            && \SmallSmallRSS\Sanity::getSchemaVersion() >= 120) {
            $pref_locale = \SmallSmallRSS\DBPrefs::read('USER_LANGUAGE', $owner_uid);
            if ($pref_locale && $pref_locale != 'auto') {
                $locale = $pref_locale;
            }
        }
        if ($locale) {
            if (defined('LC_MESSAGES')) {
                _setlocale(LC_MESSAGES, $locale);
            } elseif (defined('LC_ALL')) {
                _setlocale(LC_ALL, $locale);
            }
            _bindtextdomain('messages', __DIR__ . '/locale');
            _textdomain('messages');
            _bind_textdomain_codeset('messages', 'UTF-8');
        }
    }
}
