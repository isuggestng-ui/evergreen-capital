<?php
/** Every page starts here. */

$root = dirname(dirname(__FILE__));

if (!file_exists($root . '/config.php')) {
    // No hand-written config.php. On Railway (and any env-driven host) we build
    // configuration from environment variables instead of showing a setup page.
    if (file_exists($root . '/config.railway.php')) {
        require_once $root . '/config.railway.php';
    }
}

if (!defined('DB_DRIVER') && !file_exists($root . '/config.php')) {
    // Neither a config.php nor any usable environment configuration exists.
    // No redirect here. Redirecting to install.php caused an endless loop when
    // that file was absent: 404 -> ErrorDocument -> index.php -> redirect -> 404.
    // A plain page cannot loop, whether or not an installer exists.
    if (basename($_SERVER['SCRIPT_NAME']) === 'install.php') return;

    http_response_code(503);
    header('Retry-After: 3600');
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Setup required</title>
<style>
  body{font:400 16px/1.65 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#22302B;
       background:#F7F6F1;margin:0;padding:14vh 20px}
  .box{max-width:560px;margin:0 auto;background:#fff;border:1px solid #E3E7E4;border-radius:14px;padding:34px}
  h1{font:400 27px/1.25 Georgia,serif;margin:0 0 6px}
  p{margin:0 0 14px}
  ol{padding-left:20px;margin:0 0 6px}
  li{margin-bottom:11px}
  code{background:#F2F5F3;border:1px solid #E3E7E4;border-radius:4px;padding:1px 6px;
       font:500 13.5px/1 ui-monospace,Menlo,Consolas,monospace}
  .note{color:#6B7671;font-size:14px;margin-top:20px;padding-top:16px;border-top:1px solid #EDEFEE}
</style></head><body><div class="box">
<h1>Setup required</h1>
<p>This site has no <code>config.php</code> yet, so it cannot reach a database.</p>
<ol>
  <li>In cPanel, create a MySQL database and a user, and add that user to the
      database with all privileges.</li>
  <li>In phpMyAdmin, select the database, open <strong>Import</strong>, and import
      <code>evergreen-mysql.sql</code>.</li>
  <li>Rename <code>config-sample.php</code> to <code>config.php</code> and fill in the
      database name, user and password.</li>
</ol>
<p class="note">Nothing else is needed — there is no installer to run. Once
<code>config.php</code> exists, this message disappears by itself.</p>
</div></body></html><?php
    exit;
}

// Load a hand-written config.php if present (env-based config was already loaded above).
if (file_exists($root . '/config.php')) {
    require_once $root . '/config.php';
}

if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

date_default_timezone_set('UTC');

require_once $root . '/inc/db.php';
require_once $root . '/inc/helpers.php';
require_once $root . '/inc/i18n.php';
require_once $root . '/inc/bank.php';
require_once $root . '/inc/mailer.php';
require_once $root . '/inc/layout.php';

// ── First-run auto-install ──────────────────────────────────────────────
// On cPanel you import the .sql by hand. Railway has no phpMyAdmin, so we
// create the tables and seed the admin + settings automatically the first
// time the app connects. This is idempotent: it runs only when the "users"
// table is missing, so it never overwrites live data on later boots.
try {
    $__pdo = db();
    $__needsInstall = false;
    try {
        $__pdo->query('SELECT 1 FROM users LIMIT 1');
    } catch (Exception $e) {
        $__needsInstall = true;
    }
    if ($__needsInstall) {
        require_once $root . '/inc/install_seed.php';
        bankdom_auto_install_and_seed($__pdo);
    }
} catch (Exception $e) {
    // If the database itself is unreachable, show a clean message instead of a
    // raw 500 stack trace.
    if (!(defined('APP_DEBUG') && APP_DEBUG)) {
        http_response_code(503);
        header('Retry-After: 30');
        echo '<!doctype html><meta charset="utf-8"><title>Starting up</title>';
        echo '<div style="font:400 16px/1.6 Helvetica,Arial,sans-serif;max-width:520px;margin:16vh auto;padding:0 20px;color:#22302B">';
        echo '<h1 style="font:400 28px/1.2 Georgia,serif">Starting up…</h1>';
        echo '<p>The application is connecting to its database. If you just deployed, '
           . 'give it a few seconds and refresh. If this persists, confirm a database '
           . 'plugin is attached in Railway.</p></div>';
        exit;
    }
    throw $e;
}

// ── One-time rebrand for databases created under the old name ───────────
// Existing installs keep their data; this only swaps values that are still the
// old factory defaults (name, language, rouble currency). Runs once.
try {
    if (setting('brand_rev') !== 'evergreen-1') {
        $__brand = 'Evergreen Capital';
        if (in_array(setting('site_name'), array('', 'Bank DOM', 'Cirtus', 'Банк ДОМ', 'Veridian Trust Bank'), true)) setting_set('site_name', $__brand);
        if (in_array(setting('mail_from_name'), array('', 'Bank DOM', 'Cirtus', 'Veridian Trust Bank'), true)) setting_set('mail_from_name', $__brand);
        if (setting('tagline') === 'Банк, который всегда рядом') setting_set('tagline', 'Banking built on trust');
        if (setting('default_lang') === 'ru') setting_set('default_lang', 'en');
        if (setting('currency_code') === 'RUB') {
            setting_set('currency_code', 'USD');
            setting_set('currency_symbol', '$');
            db()->exec("UPDATE users SET currency = 'USD' WHERE currency = 'RUB'");
        }
        if (strpos(setting('admin_notice'), 'Демо') === 0) {
            setting_set('admin_notice', 'Demonstration platform — not a real bank. No real deposits are held.');
        }
        setting_set('brand_rev', 'evergreen-1');
    }
} catch (Exception $e) {
    // never block a page over a cosmetic migration
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/', '', !empty($_SERVER['HTTPS']), true);
    session_name('evergreen_sess');
    session_start();
}

// Resolve the interface language and persist the choice BEFORE any HTML output,
// so the cookie is actually written. (Setting it later, mid-page, silently fails
// once headers are sent — Safari in particular then never switches.)
init_lang();

// Idle timeout: 30 minutes
if (!empty($_SESSION['uid'])) {
    if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] > 1800) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash']['info'] = 'You were signed out after 30 minutes of inactivity.';
    } else {
        $_SESSION['seen'] = time();
    }
}

// Maintenance mode locks out customers but not admins.
if (setting('maintenance', '0') === '1' && !is_admin()) {
    $allow = array('login.php');
    if (!in_array(basename($_SERVER['SCRIPT_NAME']), $allow, true) && strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false) {
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><title>Scheduled maintenance</title>';
        echo '<div style="font:400 16px/1.6 Helvetica,Arial,sans-serif;max-width:520px;margin:16vh auto;padding:0 20px;color:#22302B">';
        echo '<h1 style="font:400 28px/1.2 Georgia,serif">Back shortly</h1>';
        echo '<p>' . e(setting('site_name')) . ' is down for scheduled maintenance. Your funds and records are untouched.</p>';
        echo '<p style="color:#7A8480">Need help now? ' . e(setting('support_email')) . '</p></div>';
        exit;
    }
}
