<?php
/**
 * Evergreen Capital — first-run installer/seeder for environment-driven hosts (Railway).
 *
 * Mirrors what the bundled evergreen-mysql.sql / evergreen-postgres.sql do when
 * imported by hand: create every table, insert the default settings, and create
 * the initial administrator. Safe to call repeatedly — it only seeds rows that
 * are missing, so it never clobbers data on subsequent deploys.
 */

function bankdom_auto_install_and_seed(PDO $pdo)
{
    // 1. Create all tables + indexes using the schema builder already in inc/db.php.
    db_install();

    // 2. Seed settings (only keys that don't exist yet).
    $defaults = array(
        'site_name' => 'Evergreen Capital',
        'site_legal' => '',
        'tagline' => 'Banking built on trust',
        'currency_code' => 'USD',
        'currency_symbol' => '$',
        'support_email' => '',
        'support_phone' => '',
        'address' => '',
        'bik' => '',
        'swift_code' => '',
        'corr_account' => '',
        'inn' => '',
        'footer_note' => '',
        'admin_notice' => 'Demonstration platform — not a real bank. No real deposits are held.',
        'default_lang' => 'en',
        'show_demo_badge' => '1',
        'legal_notice' => '',
        'mail_method' => 'php',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_secure' => 'tls',
        'mail_from' => 'no-reply@example.com',
        'mail_from_name' => 'Evergreen Capital',
        'registration_open' => '1',
        'verify_email' => '1',
        'auto_activate' => '0',
        'welcome_bonus' => '0',
        'maintenance' => '0',
        'approve_deposits' => '1',
        'approve_withdrawals' => '1',
        'approve_transfers' => '1',
        'approve_internal' => '0',
        'require_pin' => '1',
        'require_otp' => '0',
        'enable_codes' => '0',
        'code_sequence' => 'cot,imf,tax',
        'fee_percent' => '0.5',
        'fee_flat' => '0',
        'min_transfer' => '1',
        'max_transfer' => '250000',
        'daily_limit' => '100000',
        'min_deposit' => '10',
        'loans_enabled' => '1',
        'loan_rate' => '4.5',
        'loan_min' => '500',
        'loan_max' => '100000',
        'loan_tenures' => '3,6,12,24,36,48',
        'cards_enabled' => '1',
        'card_fee' => '0',
    );

    // Allow overriding the seeded site name / default language via env at first run.
    if (($n = getenv('SITE_NAME')) !== false && $n !== '') $defaults['site_name'] = $n;
    if (($l = getenv('DEFAULT_LANG')) !== false && $l !== '') $defaults['default_lang'] = $l;

    $ins = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $exists = $pdo->prepare('SELECT 1 FROM settings WHERE skey = ?');
        $exists->execute(array($k));
        if (!$exists->fetch()) {
            $ins->execute(array($k, $v));
        }
    }

    // 3. Seed the administrator if no admin exists yet.
    $hasAdmin = $pdo->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if (!$hasAdmin) {
        // Credentials can be overridden with env vars; otherwise fall back to the
        // documented defaults. CHANGE THESE after first login.
        $adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@evergreencapital.com';
        $adminPass  = getenv('ADMIN_PASSWORD') ?: 'Evergreen#2026';
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);

        $now = date('Y-m-d H:i:s');
        $acct = (string) random_int(1000000000, 9999999999);

        $stmt = $pdo->prepare(
            'INSERT INTO users
              (first_name, last_name, email, password, role, status,
               account_number, account_type, currency, balance, email_verified,
               kyc_status, country, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            'Site', 'Administrator', $adminEmail, $hash, 'admin', 'active',
            $acct, 'Administration', 'USD', 0, 1, 'verified', '', $now,
        ));
    }
}
