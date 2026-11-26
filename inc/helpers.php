<?php
/** Shared helpers. */

/* ---------- settings ---------- */

function settings_all($fresh = false)
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = array();
        try {
            $rows = db()->query('SELECT skey, svalue FROM settings')->fetchAll();
            foreach ($rows as $r) $cache[$r['skey']] = $r['svalue'];
        } catch (Exception $e) {
            $cache = array();
        }
    }
    return $cache;
}

function setting($key, $default = '')
{
    $all = settings_all();
    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}

function setting_set($key, $value)
{
    $pdo = db();
    $st = $pdo->prepare('SELECT skey FROM settings WHERE skey = ?');
    $st->execute(array($key));
    if ($st->fetch()) {
        $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?')->execute(array($value, $key));
    } else {
        $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)')->execute(array($key, $value));
    }
    settings_all(true);
}

function default_settings()
{
    return array(
        'site_name'         => 'Evergreen Capital',
        'site_legal'        => '',
        'tagline'           => 'Banking built on trust',
        'currency_code'     => 'USD',
        'currency_symbol'   => '$',
        'support_email'     => '',
        'support_phone'     => '',
        'address'           => '',
        'bik'               => '',
        'swift_code'        => '',
        'corr_account'      => '',
        'inn'               => '',
        'footer_note'       => '',
        'admin_notice'      => 'Demonstration platform — not a real bank. No real deposits are held.',
        'default_lang'      => 'en',
        'show_demo_badge'   => '1',
        'legal_notice'      => '',

        'mail_method'       => 'php',
        'smtp_host'         => '',
        'smtp_port'         => '587',
        'smtp_user'         => '',
        'smtp_pass'         => '',
        'smtp_secure'       => 'tls',
        'mail_from'         => 'no-reply@example.com',
        'mail_from_name'    => 'Evergreen Capital',

        'registration_open' => '1',
        'verify_email'      => '1',
        'auto_activate'     => '0',
        'welcome_bonus'     => '0',
        'maintenance'       => '0',

        'approve_deposits'    => '1',
        'approve_withdrawals' => '1',
        'approve_transfers'   => '1',
        'approve_internal'    => '0',

        'require_pin'       => '1',
        'require_otp'       => '0',
        'enable_codes'      => '0',
        'code_sequence'     => 'cot,imf,tax',

        'fee_percent'       => '0.5',
        'fee_flat'          => '0',
        'min_transfer'      => '1',
        'max_transfer'      => '250000',
        'daily_limit'       => '100000',
        'min_deposit'       => '10',

        'loans_enabled'     => '1',
        'loan_rate'         => '4.5',
        'loan_min'          => '500',
        'loan_max'          => '100000',
        'loan_tenures'      => '3,6,12,24,36,48',

        'cards_enabled'     => '1',
        'card_fee'          => '0',
    );
}

/* ---------- money & text ---------- */

function money($amount, $withSymbol = true)
{
    $sym = setting('currency_symbol', '$');
    $neg = $amount < 0;
    // Russian rouble style: "1 234,56 ₽" — space thousands, comma decimals,
    // symbol after the amount. Other currencies keep the symbol in front.
    if ($sym === "\xE2\x82\xBD" || setting('currency_code', 'USD') === 'RUB') {
        $n = number_format(abs((float)$amount), 2, ',', "\xC2\xA0"); // nbsp thousands
        $out = $n . ($withSymbol ? "\xC2\xA0" . $sym : '');
    } else {
        $out = ($withSymbol ? $sym : '') . number_format(abs((float)$amount), 2);
    }
    return $neg ? '-' . $out : $out;
}

function cur()
{
    return setting('currency_symbol', '$');
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function nice_date($d, $withTime = true)
{
    if (!$d) return '—';
    $ts = strtotime($d);
    if (!$ts) return e($d);
    return date($withTime ? 'M j, Y · g:ia' : 'M j, Y', $ts);
}

function initials($first, $last)
{
    return strtoupper(substr((string)$first, 0, 1) . substr((string)$last, 0, 1));
}

function str_short($s, $n = 60)
{
    $s = trim((string)$s);
    return strlen($s) > $n ? substr($s, 0, $n - 1) . '…' : $s;
}

/* ---------- ids & tokens ---------- */

function make_ref($prefix = 'TXN')
{
    return $prefix . date('ymd') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
}

function make_token($len = 32)
{
    return bin2hex(random_bytes($len / 2));
}

function make_account_number()
{
    $pdo = db();
    for ($i = 0; $i < 40; $i++) {
        $n = '30' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $st = $pdo->prepare('SELECT id FROM users WHERE account_number = ?');
        $st->execute(array($n));
        if (!$st->fetch()) return $n;
    }
    return '30' . time();
}

function make_otp()
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/* ---------- urls ---------- */

function base_url($path = '')
{
    static $base = null;
    if ($base === null) {
        if (defined('APP_URL') && APP_URL !== '') {
            $base = rtrim(APP_URL, '/');
        } else {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $root = str_replace('\\', '/', dirname(dirname(__FILE__)));
            $doc  = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
            $sub  = '';
            if ($doc && strpos($root, $doc) === 0) $sub = substr($root, strlen($doc));
            $base = ($https ? 'https://' : 'http://') . $host . rtrim($sub, '/');
        }
    }
    return $base . ($path ? '/' . ltrim($path, '/') : '');
}

function redirect($path)
{
    $url = (strpos($path, 'http') === 0) ? $path : base_url($path);
    header('Location: ' . $url);
    exit;
}

function current_file()
{
    return basename($_SERVER['SCRIPT_NAME']);
}

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/* ---------- csrf ---------- */

function csrf_token()
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = make_token(40);
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check()
{
    $t = isset($_POST['_token']) ? $_POST['_token'] : '';
    if (!$t || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        flash('error', 'That form expired. Please try again.');
        redirect(current_dir_path());
    }
}

function current_dir_path()
{
    $p = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    return strtok($p, '?');
}

/* ---------- flash ---------- */

function flash($type, $msg = null)
{
    if ($msg === null) {
        if (empty($_SESSION['flash'][$type])) return null;
        $m = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $m;
    }
    $_SESSION['flash'][$type] = $msg;
    return null;
}

function render_flash()
{
    $out = '';
    foreach (array('success', 'error', 'info') as $t) {
        $m = flash($t);
        if ($m) $out .= '<div class="alert alert-' . $t . '">' . e($m) . '</div>';
    }
    return $out;
}

/* ---------- auth ---------- */

function auth_user($fresh = false)
{
    static $u = null;
    if ($u !== null && !$fresh) return $u;
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute(array($_SESSION['uid']));
    $u = $st->fetch();
    if (!$u) { unset($_SESSION['uid']); return null; }
    return $u;
}

function require_user()
{
    $u = auth_user();
    if (!$u) { flash('error', 'Please sign in to continue.'); redirect('login.php'); }
    if ($u['status'] === 'suspended') {
        session_unset();
        session_destroy();
        session_start();
        flash('error', 'This account is suspended. Contact support.');
        redirect('login.php');
    }
    return $u;
}

function require_admin()
{
    $u = auth_user();
    if (!$u || $u['role'] !== 'admin') {
        flash('error', 'Administrator sign-in required.');
        redirect('admin/login.php');
    }
    return $u;
}

function is_admin()
{
    $u = auth_user();
    return $u && $u['role'] === 'admin';
}

function find_user($id)
{
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute(array($id));
    return $st->fetch();
}

function user_name($u)
{
    return trim($u['first_name'] . ' ' . $u['last_name']);
}

/* ---------- audit ---------- */

function audit($action, $entity = '', $entityId = 0, $details = '')
{
    $u = auth_user();
    try {
        db()->prepare('INSERT INTO audit_log (actor_id, actor_name, action, entity, entity_id, details, ip, created_at)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute(array(
                $u ? $u['id'] : null,
                $u ? user_name($u) : 'system',
                $action, $entity, $entityId, $details, client_ip(), now()
            ));
    } catch (Exception $e) { /* never break a flow for logging */ }
}

function now()
{
    return date('Y-m-d H:i:s');
}

/* ---------- misc ---------- */

function post($key, $default = '')
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get($key, $default = '')
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function amount_in($key)
{
    $v = str_replace(array(',', ' '), '', post($key, '0'));
    return round((float)$v, 2);
}

function status_pill($status)
{
    $map = array(
        'completed' => 'ok', 'approved' => 'ok', 'active' => 'ok', 'verified' => 'ok', 'repaid' => 'ok',
        'pending'   => 'wait', 'processing' => 'wait', 'unverified' => 'wait',
        'declined'  => 'bad', 'failed' => 'bad', 'reversed' => 'bad', 'suspended' => 'bad', 'defaulted' => 'bad',
    );
    $k = isset($map[$status]) ? $map[$status] : 'wait';
    return '<span class="pill pill-' . $k . '">' . e(ucfirst($status)) . '</span>';
}

function upload_file($field, $folder, $maxMb = 5)
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$field];
    if ($f['size'] > $maxMb * 1024 * 1024) return null;
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $ok = array('jpg', 'jpeg', 'png', 'webp', 'pdf');
    if (!in_array($ext, $ok, true)) return null;
    $dir = dirname(dirname(__FILE__)) . '/uploads/' . $folder;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = date('Ymd') . '_' . make_token(12) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return null;
    return 'uploads/' . $folder . '/' . $name;
}

/** Like count_where(), but returns 0 if the table does not exist yet. */
function count_safe($table, $where = '1=1', $args = array())
{
    try { return count_where($table, $where, $args); }
    catch (Exception $e) { return 0; }
}

/* ---------- public enquiries (contact form, job applications) ---------- */

/** Create the enquiries table if an older install predates it. Cheap, only run on submit. */
function ensure_enquiries_table()
{
    $my = db_is_mysql();
    $id = $my ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS enquiries (
            id $id, kind VARCHAR(20), name VARCHAR(120), email VARCHAR(160), phone VARCHAR(40),
            subject VARCHAR(200), message TEXT, meta TEXT, status VARCHAR(20) DEFAULT 'new',
            admin_note TEXT, created_at DATETIME)$suffix");
    } catch (Exception $e) { /* already there */ }
}

/**
 * Store a public enquiry and alert the bank by email.
 * $kind: contact | application
 */
function save_enquiry($kind, $name, $email, $phone, $subject, $message, $meta = '')
{
    ensure_enquiries_table();
    db()->prepare('INSERT INTO enquiries (kind, name, email, phone, subject, message, meta, status, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute(array($kind, $name, $email, $phone, $subject, $message, $meta, 'new', now()));

    $label = $kind === 'application' ? 'Job application' : 'Website enquiry';
    $body  = '<p><strong>' . e($name) . '</strong> &lt;' . e($email) . '&gt;'
           . ($phone ? ' · ' . e($phone) : '') . '</p>'
           . '<p><strong>' . e($subject) . '</strong></p>'
           . '<p>' . nl2br(e($message)) . '</p>'
           . ($meta ? '<p class="muted">' . e($meta) . '</p>' : '');

    $seen = array();
    foreach (db()->query("SELECT email FROM users WHERE role='admin'")->fetchAll() as $a) {
        $seen[strtolower($a['email'])] = true;
        send_mail($a['email'], $label . ': ' . $subject,
            mail_template($label, $body, 'Open enquiries', base_url('admin/enquiries.php')));
    }
    $support = setting('support_email');
    if ($support && !isset($seen[strtolower($support)])) {
        send_mail($support, $label . ': ' . $subject, mail_template($label, $body));
    }

    // Acknowledge to the sender.
    send_mail($email, 'We have your message', mail_template('Thank you — we have it',
        '<p>Hello ' . e($name) . ',</p>
         <p>Thanks for getting in touch. Someone will come back to you within one working day.</p>
         <p><strong>' . e($subject) . '</strong><br>' . nl2br(e(str_short($message, 400))) . '</p>'));

    return true;
}
