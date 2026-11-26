<?php
/**
 * Evergreen Capital — Railway / environment auto-configuration.
 *
 * On cPanel you rename config-sample.php to config.php by hand. On Railway there
 * is no File Manager, so this file builds the same DB_* / APP_* constants from
 * environment variables that Railway injects when you add a database plugin.
 *
 * bootstrap.php loads this automatically when a hand-written config.php is absent,
 * so the app is fully configured on first boot with zero manual editing.
 *
 * Supported inputs (checked in order):
 *   1. DATABASE_URL / MYSQL_URL / MYSQL_PUBLIC_URL  (Railway MySQL plugin)
 *   2. DATABASE_URL beginning postgres://           (Railway/Neon Postgres plugin)
 *   3. Individual PG or MYSQL prefixed variables
 *   4. Nothing set  -> falls back to bundled SQLite, so the site still boots.
 */

if (defined('DB_DRIVER')) {
    return; // A real config.php already defined everything; do nothing.
}

/* ---- helper: first non-empty environment variable from a list ---- */
$__env = function (array $keys, $default = '') {
    foreach ($keys as $k) {
        $v = getenv($k);
        if ($v !== false && $v !== '') return $v;
        if (isset($_ENV[$k]) && $_ENV[$k] !== '') return $_ENV[$k];
        if (isset($_SERVER[$k]) && $_SERVER[$k] !== '') return $_SERVER[$k];
    }
    return $default;
};

/* ---- 1. Look for a single connection URL first ---- */
$url = $__env(array('DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL', 'POSTGRES_URL'));

$driver = '';
$host = '';
$port = '';
$name = '';
$user = '';
$pass = '';

if ($url !== '') {
    $p = parse_url($url);
    if ($p !== false && isset($p['scheme'])) {
        $scheme = strtolower($p['scheme']);
        if (strpos($scheme, 'postgres') === 0 || $scheme === 'pgsql') {
            $driver = 'pgsql';
            $port = isset($p['port']) ? (string) $p['port'] : '5432';
        } else {
            $driver = 'mysql';
            $port = isset($p['port']) ? (string) $p['port'] : '3306';
        }
        $host = isset($p['host']) ? $p['host'] : '';
        $user = isset($p['user']) ? rawurldecode($p['user']) : '';
        $pass = isset($p['pass']) ? rawurldecode($p['pass']) : '';
        $name = isset($p['path']) ? ltrim($p['path'], '/') : '';
    }
}

/* ---- 2. No URL? Assemble from individual Railway variables ---- */
if ($driver === '') {
    // Railway Postgres plugin exposes PG* names.
    $pgHost = $__env(array('PGHOST', 'POSTGRES_HOST'));
    if ($pgHost !== '') {
        $driver = 'pgsql';
        $host = $pgHost;
        $port = $__env(array('PGPORT'), '5432');
        $name = $__env(array('PGDATABASE', 'POSTGRES_DB'), 'railway');
        $user = $__env(array('PGUSER', 'POSTGRES_USER'), 'postgres');
        $pass = $__env(array('PGPASSWORD', 'POSTGRES_PASSWORD'));
    } else {
        // Railway MySQL plugin exposes MYSQL* names.
        $myHost = $__env(array('MYSQLHOST', 'MYSQL_HOST'));
        if ($myHost !== '') {
            $driver = 'mysql';
            $host = $myHost;
            $port = $__env(array('MYSQLPORT', 'MYSQL_PORT'), '3306');
            $name = $__env(array('MYSQLDATABASE', 'MYSQL_DATABASE'), 'railway');
            $user = $__env(array('MYSQLUSER', 'MYSQL_USER'), 'root');
            $pass = $__env(array('MYSQLPASSWORD', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD'));
        }
    }
}

/* ---- 3. Still nothing? Fall back to SQLite so the site never hard-fails ---- */
if ($driver === '') {
    define('DB_DRIVER', 'sqlite');
    define('DB_HOST', '');
    define('DB_NAME', '');
    define('DB_USER', '');
    define('DB_PASS', '');
    define('DB_PORT', '');
} else {
    define('DB_DRIVER', $driver);
    define('DB_HOST', $host);
    define('DB_NAME', $name);
    define('DB_USER', $user);
    define('DB_PASS', $pass);
    define('DB_PORT', $port);
}

define('DB_SQLITE_PATH', $__env(array('SQLITE_PATH'), __DIR__ . '/storage/bankdom.sqlite'));

/* ---- App settings ---- */
define('APP_KEY', $__env(array('APP_KEY'), 'railway-' . md5($__env(array('RAILWAY_PROJECT_ID'), 'bankdom-default-key'))));
define('APP_DEBUG', in_array(strtolower($__env(array('APP_DEBUG'), '0')), array('1', 'true', 'on'), true));
$__appurl = trim($__env(array('APP_URL', 'RAILWAY_PUBLIC_DOMAIN'), ''));
if ($__appurl !== '' && !preg_match('#^https?://#i', $__appurl)) {
    $__appurl = 'https://' . $__appurl;   // RAILWAY_PUBLIC_DOMAIN has no scheme
}
define('APP_URL', rtrim($__appurl, '/'));
