<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/authside.php';

$mode = 'token';
if (get('otp') === '1' || isset($_POST['otp'])) $mode = 'otp';
elseif (get('resend') === '1') $mode = 'resend';

/* --- confirm via emailed link --- */
if ($mode === 'token' && get('token')) {
    $st = db()->prepare('SELECT * FROM users WHERE verify_token = ?');
    $st->execute(array(get('token')));
    $u = $st->fetch();
    if ($u) {
        $status = $u['status'] === 'pending' && setting('auto_activate', '0') === '1' ? 'active' : $u['status'];
        db()->prepare('UPDATE users SET email_verified = 1, verify_token = NULL, status = ? WHERE id = ?')
            ->execute(array($status, $u['id']));
        audit('email_verified', 'user', $u['id']);
        flash('success', $status === 'active'
            ? 'Email confirmed. You can sign in now.'
            : 'Email confirmed. The bank will open your account shortly.');
    } else {
        flash('error', 'That confirmation link is no longer valid.');
    }
    redirect('login.php');
}

/* --- resend confirmation --- */
if ($mode === 'resend' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute(array(strtolower(post('email'))));
    $u = $st->fetch();
    if ($u && (int)$u['email_verified'] === 0) {
        $token = make_token(40);
        db()->prepare('UPDATE users SET verify_token = ? WHERE id = ?')->execute(array($token, $u['id']));
        send_mail($u['email'], 'Confirm your email', mail_template('Confirm your email',
            '<p>Here is a fresh confirmation link for your account.</p>',
            'Confirm email', base_url('verify.php?token=' . $token)));
    }
    flash('success', 'If that address needs confirming, a new link is on its way.');
    redirect('login.php');
}

/* --- sign-in code --- */
if ($mode === 'otp') {
    if (empty($_SESSION['otp_uid'])) redirect('login.php');
    $u = find_user($_SESSION['otp_uid']);
    if (!$u) { unset($_SESSION['otp_uid']); redirect('login.php'); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $code = post('code');
        if ($u['otp_code'] && hash_equals($u['otp_code'], $code) && strtotime($u['otp_expires']) > time()) {
            db()->prepare('UPDATE users SET otp_code = NULL, otp_expires = NULL, last_login = ?, last_ip = ? WHERE id = ?')
                ->execute(array(now(), client_ip(), $u['id']));
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            $_SESSION['seen'] = time();
            unset($_SESSION['otp_uid']);
            audit('login_2fa', 'user', $u['id']);
            redirect($u['role'] === 'admin' ? 'admin/index.php' : 'user/dashboard.php');
        }
        flash('error', 'That code is wrong or has expired.');
    }
}

layout_head($mode === 'otp' ? 'Sign-in code' : 'Confirm your email', 'auth');
auth_side();
?>
<div class="authmain">
  <div class="authcard">
    <?php auth_logo(); ?>
    <?php if ($mode === 'otp') { ?>
      <h1>Enter your code</h1>
      <p class="lead">We emailed a six-digit code to <?php echo e(preg_replace('/(.).+(@.*)/', '$1•••$2', $u['email'])); ?>. It expires in 10 minutes.</p>
      <?php echo render_flash(); ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="otp" value="1">
        <div class="field pinbox">
          <label for="code">Six-digit code</label>
          <input type="text" id="code" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required autofocus>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Continue</button>
      </form>
      <p class="authfoot"><a href="<?php echo base_url('logout.php'); ?>">Cancel and start over</a></p>
    <?php } else { ?>
      <h1>Confirm your email</h1>
      <p class="lead">Enter your address and we will send the confirmation link again.</p>
      <?php echo render_flash(); ?>
      <form method="post" action="<?php echo base_url('verify.php?resend=1'); ?>">
        <?php echo csrf_field(); ?>
        <div class="field"><label for="email">Email</label>
          <input type="email" id="email" name="email" required autofocus></div>
        <button class="btn btn-primary btn-block" type="submit">Send the link</button>
      </form>
      <p class="authfoot"><a href="<?php echo base_url('login.php'); ?>">Back to sign in</a></p>
    <?php } ?>
  </div>
</div>
<?php layout_foot('auth');
