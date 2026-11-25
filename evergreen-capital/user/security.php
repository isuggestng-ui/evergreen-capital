<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'password') {
        if (!password_verify(post('current'), $u['password'])) {
            flash('error', 'Your current password is not right.');
        } elseif (strlen(post('new')) < 8) {
            flash('error', 'The new password needs at least 8 characters.');
        } elseif (post('new') !== post('new2')) {
            flash('error', 'The two new passwords do not match.');
        } else {
            db()->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute(array(password_hash(post('new'), PASSWORD_DEFAULT), $u['id']));
            send_mail($u['email'], 'Your password changed', mail_template('Password changed',
                '<p>The password on your account changed just now from IP ' . e(client_ip()) . '.</p>
                 <p>If that was not you, contact us immediately.</p>'));
            audit('password_changed', 'user', $u['id']);
            flash('success', 'Password updated.');
        }
        redirect('user/security.php');
    }

    if (post('action') === 'pin') {
        $pin = post('pin');
        if (!preg_match('/^\d{4}$/', $pin)) {
            flash('error', 'The PIN must be exactly four digits.');
        } elseif ($pin !== post('pin2')) {
            flash('error', 'The two PINs do not match.');
        } elseif ($u['pin'] && !password_verify(post('password'), $u['password'])) {
            flash('error', 'Confirm the change with your account password.');
        } else {
            db()->prepare('UPDATE users SET pin = ? WHERE id = ?')
                ->execute(array(password_hash($pin, PASSWORD_DEFAULT), $u['id']));
            audit('pin_changed', 'user', $u['id']);
            flash('success', 'Transfer PIN set.');
        }
        redirect('user/security.php');
    }

    if (post('action') === '2fa') {
        $on = post('two_factor') === '1' ? 1 : 0;
        db()->prepare('UPDATE users SET two_factor = ? WHERE id = ?')->execute(array($on, $u['id']));
        flash('success', $on ? 'Two-step sign-in is on. We will email a code each time.' : 'Two-step sign-in is off.');
        redirect('user/security.php');
    }
}

$st = db()->prepare('SELECT * FROM login_attempts WHERE email = ? ORDER BY id DESC LIMIT 10');
$st->execute(array($u['email']));
$attempts = $st->fetchAll();

layout_head('Security', 'user');
page_title('Security', 'Your password protects the account. Your PIN protects each payment.');
?>

<div class="grid g-main" style="align-items:start">
  <div class="stack">
    <section class="card">
      <h2>Change password</h2>
      <p class="sub">Use something you do not use anywhere else.</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="password">
        <div class="field"><label>Current password</label>
          <input type="password" name="current" required autocomplete="current-password"></div>
        <div class="row2">
          <div class="field"><label>New password</label>
            <input type="password" name="new" minlength="8" required autocomplete="new-password"></div>
          <div class="field"><label>Repeat it</label>
            <input type="password" name="new2" minlength="8" required autocomplete="new-password"></div>
        </div>
        <button class="btn btn-primary" type="submit">Update password</button>
      </form>
    </section>

    <section class="card">
      <h2>Transfer PIN</h2>
      <p class="sub"><?php echo $u['pin'] ? 'A PIN is set. Enter your password to change it.' : 'No PIN yet. You need one before sending money.'; ?></p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="pin">
        <div class="row2">
          <div class="field pinbox"><label>New four-digit PIN</label>
            <input type="password" name="pin" maxlength="4" inputmode="numeric" pattern="\d{4}" required></div>
          <div class="field pinbox"><label>Repeat it</label>
            <input type="password" name="pin2" maxlength="4" inputmode="numeric" pattern="\d{4}" required></div>
        </div>
        <?php if ($u['pin']) { ?>
          <div class="field"><label>Account password</label><input type="password" name="password" required></div>
        <?php } ?>
        <button class="btn btn-primary" type="submit">Save PIN</button>
      </form>
    </section>

    <section class="card">
      <h2>Two-step sign-in</h2>
      <p class="sub">We email a six-digit code every time you sign in.</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="2fa">
        <label class="check">
          <input type="checkbox" name="two_factor" value="1" <?php echo (int)$u['two_factor'] === 1 ? 'checked' : ''; ?>>
          <span>Require an emailed code at sign-in</span>
        </label>
        <button class="btn btn-primary" type="submit">Save preference</button>
      </form>
    </section>
  </div>

  <div class="stack">
    <section class="card card-flush">
      <div class="card-head"><h2>Recent sign-in attempts</h2></div>
      <?php if (!$attempts) { ?>
        <div class="empty" style="padding:26px 12px"><p class="mb0">Nothing recorded yet.</p></div>
      <?php } else { ?>
        <div class="tablewrap"><table class="tbl">
          <thead><tr><th>When</th><th>IP</th><th>Result</th></tr></thead>
          <tbody>
          <?php foreach ($attempts as $a) { ?>
            <tr>
              <td class="small nowrap"><?php echo nice_date($a['created_at']); ?></td>
              <td class="mono small"><?php echo e($a['ip']); ?></td>
              <td><?php echo (int)$a['success'] === 1
                    ? '<span class="pill pill-ok">Success</span>'
                    : '<span class="pill pill-bad">Failed</span>'; ?></td>
            </tr>
          <?php } ?>
          </tbody></table></div>
      <?php } ?>
    </section>

    <section class="card">
      <span class="eyebrow">Good habits</span>
      <ul class="small" style="padding-left:17px;margin:0;color:var(--muted);line-height:1.8">
        <li>We will never phone or email you asking for your PIN or codes.</li>
        <li>Check the sender address on anything claiming to be from us.</li>
        <li>Report an unrecognised sign-in the same day.</li>
      </ul>
    </section>
  </div>
</div>

<?php layout_foot('user');
