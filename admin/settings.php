<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$checkboxes = array('registration_open', 'verify_email', 'auto_activate', 'maintenance',
    'approve_deposits', 'approve_withdrawals', 'approve_transfers', 'approve_internal',
    'require_pin', 'require_otp', 'enable_codes', 'loans_enabled', 'cards_enabled');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'save') {
        foreach (default_settings() as $key => $default) {
            if (in_array($key, $checkboxes, true)) {
                setting_set($key, isset($_POST[$key]) ? '1' : '0');
            } elseif (isset($_POST[$key])) {
                setting_set($key, trim($_POST[$key]));
            }
        }
        audit('settings_saved', 'settings', 0);
        flash('success', 'Settings saved.');
        redirect('admin/settings.php');
    }

    if (post('action') === 'testmail') {
        $to = post('test_to') !== '' ? post('test_to') : $admin['email'];
        $ok = send_mail($to, 'Test message from ' . setting('site_name'),
            mail_template('It works',
                '<p>This is a test message sent from your settings page at ' . nice_date(now()) . '.</p>
                 <p>If you are reading this, outgoing email is configured correctly.</p>'));
        flash($ok ? 'success' : 'error', $ok
            ? 'Test email sent to ' . $to . '. Check the inbox and the spam folder.'
            : 'The test email failed. Check the SMTP host, port and credentials.');
        redirect('admin/settings.php#email');
    }
}

$s = function ($k, $d = '') { return setting($k, $d); };
$on = function ($k) { return setting($k, '0') === '1' ? ' checked' : ''; };

layout_head('Settings', 'admin');
page_title('Settings', 'How the bank behaves. Changes take effect immediately.');
?>

<form method="post">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="save">

  <div class="grid g2" style="align-items:start">

    <section class="card">
      <span class="eyebrow">Identity</span>
      <h2>The bank</h2>
      <p class="sub">Shown across the app, on receipts and in emails.</p>
      <div class="field"><label>Trading name</label><input type="text" name="site_name" value="<?php echo e($s('site_name')); ?>"></div>
      <div class="field"><label>Legal name</label><input type="text" name="site_legal" value="<?php echo e($s('site_legal')); ?>"></div>
      <div class="field"><label>Tagline</label><input type="text" name="tagline" value="<?php echo e($s('tagline')); ?>"></div>
      <div class="row2">
        <div class="field"><label>Currency code</label><input type="text" name="currency_code" value="<?php echo e($s('currency_code')); ?>" maxlength="4"></div>
        <div class="field"><label>Currency symbol</label><input type="text" name="currency_symbol" value="<?php echo e($s('currency_symbol')); ?>" maxlength="4"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Support email</label><input type="email" name="support_email" value="<?php echo e($s('support_email')); ?>"></div>
        <div class="field"><label>Support phone</label><input type="text" name="support_phone" value="<?php echo e($s('support_phone')); ?>"></div>
      </div>
      <div class="field"><label>Registered address</label><input type="text" name="address" value="<?php echo e($s('address')); ?>"></div>
      <div class="row2">
        <div class="field"><label>Routing number</label><input type="text" name="routing_number" value="<?php echo e($s('routing_number')); ?>"></div>
        <div class="field"><label>SWIFT code</label><input type="text" name="swift_code" value="<?php echo e($s('swift_code')); ?>"></div>
      </div>

      <div class="divider"></div>
      <h2>Notices</h2>
      <p class="sub">Both are blank by default and show nothing at all until you fill them in.</p>
      <div class="field">
        <label>Footer note</label>
        <input type="text" name="footer_note" value="<?php echo e($s('footer_note')); ?>" maxlength="200">
        <span class="hint">One line, shown in the website footer and on the sign-in screen.
          Use it for your licence or registration details.</span>
      </div>
      <div class="field">
        <label>Legal page notice</label>
        <textarea name="legal_notice" rows="3" maxlength="600"><?php echo e($s('legal_notice')); ?></textarea>
        <span class="hint">Appears at the foot of the terms, privacy, security and complaints pages.</span>
      </div>
    </section>

    <section class="card">
      <span class="eyebrow">Approvals</span>
      <h2>What needs your sign-off</h2>
      <p class="sub">Anything unticked settles instantly without review.</p>
      <label class="check"><input type="checkbox" name="approve_deposits" value="1"<?php echo $on('approve_deposits'); ?>>
        <span><strong>Deposits</strong> — hold logged deposits until confirmed against your records</span></label>
      <label class="check"><input type="checkbox" name="approve_withdrawals" value="1"<?php echo $on('approve_withdrawals'); ?>>
        <span><strong>Withdrawals</strong> — hold payouts until released</span></label>
      <label class="check"><input type="checkbox" name="approve_transfers" value="1"<?php echo $on('approve_transfers'); ?>>
        <span><strong>Outward transfers</strong> — hold domestic and wire transfers</span></label>
      <label class="check"><input type="checkbox" name="approve_internal" value="1"<?php echo $on('approve_internal'); ?>>
        <span><strong>Internal transfers</strong> — also hold transfers between your own customers</span></label>

      <div class="divider"></div>
      <h2>Authorising a payment</h2>
      <p class="sub">Steps the customer must clear before money moves.</p>
      <label class="check"><input type="checkbox" name="require_pin" value="1"<?php echo $on('require_pin'); ?>>
        <span>Ask for the four-digit transfer PIN</span></label>
      <label class="check"><input type="checkbox" name="require_otp" value="1"<?php echo $on('require_otp'); ?>>
        <span>Email a one-time code for each transfer</span></label>
      <label class="check"><input type="checkbox" name="enable_codes" value="1"<?php echo $on('enable_codes'); ?>>
        <span>Require authorisation codes you set per customer</span></label>
      <div class="field"><label>Code sequence</label>
        <input type="text" name="code_sequence" value="<?php echo e($s('code_sequence')); ?>" class="mono">
        <span class="hint">Comma separated, asked in this order. Known types: cot, imf, tax, atc. Set each customer's codes on their profile.</span></div>
    </section>

    <section class="card">
      <span class="eyebrow">Money</span>
      <h2>Fees and limits</h2>
      <div class="row2">
        <div class="field"><label>Transfer fee (%)</label><input type="text" name="fee_percent" value="<?php echo e($s('fee_percent')); ?>"></div>
        <div class="field"><label>Flat fee per transfer</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" name="fee_flat" value="<?php echo e($s('fee_flat')); ?>"></div></div>
      </div>
      <div class="row2">
        <div class="field"><label>Smallest transfer</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" name="min_transfer" value="<?php echo e($s('min_transfer')); ?>"></div></div>
        <div class="field"><label>Largest transfer</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" name="max_transfer" value="<?php echo e($s('max_transfer')); ?>"></div></div>
      </div>
      <div class="row2">
        <div class="field"><label>Daily outgoing limit</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" name="daily_limit" value="<?php echo e($s('daily_limit')); ?>"></div></div>
        <div class="field"><label>Smallest deposit</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" name="min_deposit" value="<?php echo e($s('min_deposit')); ?>"></div></div>
      </div>

      <div class="divider"></div>
      <h2>Lending</h2>
      <label class="check"><input type="checkbox" name="loans_enabled" value="1"<?php echo $on('loans_enabled'); ?>>
        <span>Accept loan applications</span></label>
      <div class="row3">
        <div class="field"><label>Rate (% a year)</label><input type="text" name="loan_rate" value="<?php echo e($s('loan_rate')); ?>"></div>
        <div class="field"><label>Minimum</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" name="loan_min" value="<?php echo e($s('loan_min')); ?>"></div></div>
        <div class="field"><label>Maximum</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" name="loan_max" value="<?php echo e($s('loan_max')); ?>"></div></div>
      </div>
      <div class="field"><label>Terms offered (months)</label>
        <input type="text" name="loan_tenures" value="<?php echo e($s('loan_tenures')); ?>" class="mono">
        <span class="hint">Comma separated.</span></div>

      <div class="divider"></div>
      <h2>Cards</h2>
      <label class="check"><input type="checkbox" name="cards_enabled" value="1"<?php echo $on('cards_enabled'); ?>>
        <span>Accept card requests</span></label>
      <div class="field"><label>Issuing fee</label>
        <div class="money-in"><span><?php echo e(cur()); ?></span>
          <input type="text" name="card_fee" value="<?php echo e($s('card_fee')); ?>"></div></div>
    </section>

    <section class="card" id="email">
      <span class="eyebrow">Accounts</span>
      <h2>Registration</h2>
      <label class="check"><input type="checkbox" name="registration_open" value="1"<?php echo $on('registration_open'); ?>>
        <span>Let people open accounts from the website</span></label>
      <label class="check"><input type="checkbox" name="verify_email" value="1"<?php echo $on('verify_email'); ?>>
        <span>Make them confirm their email first</span></label>
      <label class="check"><input type="checkbox" name="auto_activate" value="1"<?php echo $on('auto_activate'); ?>>
        <span>Open new accounts automatically instead of holding them for review</span></label>
      <div class="field"><label>Welcome bonus credited on sign-up</label>
        <div class="money-in"><span><?php echo e(cur()); ?></span>
          <input type="text" name="welcome_bonus" value="<?php echo e($s('welcome_bonus')); ?>"></div>
        <span class="hint">Set to 0 for none.</span></div>

      <div class="divider"></div>
      <h2>Outgoing email</h2>
      <p class="sub">SMTP is more reliable than the server's mail function and far less likely to land in spam.</p>
      <div class="field"><label>Method</label>
        <select name="mail_method">
          <option value="php"<?php echo $s('mail_method') === 'php' ? ' selected' : ''; ?>>PHP mail() — no setup</option>
          <option value="smtp"<?php echo $s('mail_method') === 'smtp' ? ' selected' : ''; ?>>SMTP — recommended</option>
        </select></div>
      <div class="row2">
        <div class="field"><label>From address</label><input type="email" name="mail_from" value="<?php echo e($s('mail_from')); ?>"></div>
        <div class="field"><label>From name</label><input type="text" name="mail_from_name" value="<?php echo e($s('mail_from_name')); ?>"></div>
      </div>
      <div class="row3">
        <div class="field"><label>SMTP host</label><input type="text" name="smtp_host" value="<?php echo e($s('smtp_host')); ?>" placeholder="mail.yourdomain.com"></div>
        <div class="field"><label>Port</label><input type="text" name="smtp_port" value="<?php echo e($s('smtp_port')); ?>"></div>
        <div class="field"><label>Encryption</label>
          <select name="smtp_secure">
            <?php foreach (array('tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None (25)') as $k => $lbl)
              echo '<option value="' . $k . '"' . ($s('smtp_secure') === $k ? ' selected' : '') . '>' . $lbl . '</option>'; ?>
          </select></div>
      </div>
      <div class="row2">
        <div class="field"><label>SMTP username</label><input type="text" name="smtp_user" value="<?php echo e($s('smtp_user')); ?>" autocomplete="off"></div>
        <div class="field"><label>SMTP password</label><input type="password" name="smtp_pass" value="<?php echo e($s('smtp_pass')); ?>" autocomplete="new-password"></div>
      </div>

      <div class="divider"></div>
      <h2>Maintenance</h2>
      <label class="check"><input type="checkbox" name="maintenance" value="1"<?php echo $on('maintenance'); ?>>
        <span><strong>Maintenance mode</strong> — customers see a holding page. Staff can still sign in and work.</span></label>
    </section>
  </div>

  <div class="card" style="margin-top:18px;position:sticky;bottom:14px">
    <div class="flexbar">
      <button class="btn btn-primary" type="submit">Save all settings</button>
      <span class="small muted">Everything on this page is saved together.</span>
    </div>
  </div>
</form>

<section class="card" style="margin-top:18px">
  <span class="eyebrow">Check your setup</span>
  <h2>Send a test email</h2>
  <p class="sub">Save your settings first, then send yourself a test.</p>
  <form method="post" class="flexbar" style="gap:10px">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="testmail">
    <input type="email" name="test_to" value="<?php echo e($admin['email']); ?>" style="flex:1;min-width:220px">
    <button class="btn" type="submit" data-busy-text="Sending…">Send test</button>
  </form>
</section>

<?php layout_foot('admin');
