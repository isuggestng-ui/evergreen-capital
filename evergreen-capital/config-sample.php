<?php
/* Evergreen Capital — configuration. Copy to config.php and fill in ONE database block. */

/* ══ Option A: MySQL (cPanel / shared hosting) ══ */
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'yourcpanel_bankdom');
define('DB_USER', 'yourcpanel_bankdom');
define('DB_PASS', 'your-database-password');
define('DB_PORT', '3306');

/* ══ Option B: Postgres / Neon (comment out A, uncomment this) ══
   Use the POOLED host — the one containing "-pooler".
define('DB_DRIVER', 'pgsql');
define('DB_HOST', 'ep-xxxx-pooler.region.aws.neon.tech');
define('DB_NAME', 'neondb');
define('DB_USER', 'neondb_owner');
define('DB_PASS', 'your-neon-password');
define('DB_PORT', '5432');
*/

define('DB_SQLITE_PATH', __DIR__ . '/storage/bankdom.sqlite');
define('APP_KEY', 'CHANGE-THIS-TO-A-LONG-RANDOM-STRING');
define('APP_DEBUG', false);
define('APP_URL', '');
