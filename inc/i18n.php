<?php
/**
 * Lightweight bilingual layer — English and Russian.
 *
 * Usage in any page:   echo t('nav.dashboard');
 * With variables:      echo t('greeting.hello', array('name' => $u['first_name']));
 *
 * The active language is a cookie ("lang"), toggled from the header, defaulting
 * to the site's "default_lang" setting. Missing keys fall back to English, then
 * to the key itself, so a page never renders blank.
 */

function lang_available()
{
    return array('de' => 'Deutsch', 'en' => 'English', 'ru' => 'Русский');
}

function current_lang()
{
    static $lang = null;
    if ($lang !== null) return $lang;

    $avail = lang_available();
    // URL choice wins for the current request.
    if (isset($_GET['lang']) && isset($avail[$_GET['lang']])) return $lang = $_GET['lang'];
    if (isset($_COOKIE['lang']) && isset($avail[$_COOKIE['lang']])) return $lang = $_COOKIE['lang'];

    $default = function_exists('setting') ? setting('default_lang', 'en') : 'en';
    return $lang = isset($avail[$default]) ? $default : 'en';
}

/**
 * Persist the language choice. MUST run before any output (called from
 * bootstrap), because setcookie() fails once headers are sent — which is what
 * made the toggle appear dead on Safari.
 */
function init_lang()
{
    $avail = lang_available();
    if (isset($_GET['lang']) && isset($avail[$_GET['lang']])) {
        $choice = $_GET['lang'];
        if (!headers_sent()) {
            setcookie('lang', $choice, array(
                'expires'  => time() + 31536000,
                'path'     => '/',
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly' => false,
            ));
        }
        $_COOKIE['lang'] = $choice; // reflect immediately for this request
    }
}

function lang_dir()
{
    return 'ltr'; // both languages are left-to-right
}

/** Translate a key. $vars values replace {placeholders}. */
function t($key, $vars = array())
{
    static $dict = null;
    if ($dict === null) $dict = require __DIR__ . '/lang.php';

    $lang = current_lang();
    $s = null;
    if (isset($dict[$lang][$key])) $s = $dict[$lang][$key];
    elseif (isset($dict['en'][$key])) $s = $dict['en'][$key];
    else $s = $key;

    if ($vars) {
        foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', $v, $s);
    }
    return $s;
}

/** Same as t() but HTML-escaped, for safe echoing into markup. */
function te($key, $vars = array())
{
    return e(t($key, $vars));
}

/** A small language switcher (used in headers). */
function lang_switcher($class = 'langswitch')
{
    $cur = current_lang();
    $out = '<div class="' . e($class) . '">';
    foreach (lang_available() as $code => $label) {
        $sep = strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?';
        // strip any existing lang param to avoid stacking
        $uri = preg_replace('/([?&])lang=[a-z]{2}(&|$)/', '$1', $_SERVER['REQUEST_URI']);
        $uri = rtrim($uri, '?&');
        $sep = strpos($uri, '?') !== false ? '&' : '?';
        $on = $code === $cur ? ' on' : '';
        $out .= '<a class="langopt' . $on . '" href="' . e($uri . $sep . 'lang=' . $code) . '" hreflang="' . $code . '">'
              . strtoupper($code) . '</a>';
    }
    return $out . '</div>';
}
