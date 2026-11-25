<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/authside.php';

$token = get('token') ? get('token') : post('token');
$st = db()->prepare('SELECT * FROM users WHERE reset_token = ?');
$st->execute(array($token));
$u = $token ? $st->fetch() : null;

if (!$u || strtotime($u['reset_expires']) < time()) {
    flash('error', 'That reset link has expired. Request a fresh one.');
    redirect('forgot.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $p1 = post('password');
    $p2 = post('password2');
    if (strlen($p1) < 8) {
        flash('error', 'Use at least 8 characters.');
    } elseif ($p1 !== $p2) {
        flash('error', 'The two passwords do not match.');
    } else {
        db()->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
            ->execute(array(password_hash($p1, PASSWORD_DEFAULT), $u['id']));
        send_mail($u['email'], 'Your password changed', mail_template('Password changed',
            '<p>The password on your account was just changed from IP ' . e(client_ip()) . '.</p>
             <p>If that wasn\'t you, contact us immediately.</p>'));
        audit('password_reset', 'user', $u['id']);
        flash('success', 'Password updated. Sign in with your new one.');
        redirect('login.php');
    }
}

layout_head('Choose a new password', 'auth');
auth_side();
?>
<div class="authmain">
  <div class="authcard">
    <?php auth_logo(); ?>
    <h1>Choose a new password</h1>
    <p class="lead">You are resetting the password for <?php echo e($u['email']); ?>.</p>
    <?php echo render_flash(); ?>
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="token" value="<?php echo e($token); ?>">
      <div class="field"><label for="p1">New password</label>
        <input type="password" id="p1" name="password" minlength="8" required autofocus autocomplete="new-password"></div>
      <div class="field"><label for="p2">Repeat it</label>
        <input type="password" id="p2" name="password2" minlength="8" required autocomplete="new-password"></div>
      <button class="btn btn-primary btn-block" type="submit">Save new password</button>
    </form>
  </div>
</div>
<?php layout_foot('auth');
