<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/authside.php';

if (auth_user()) redirect(is_admin() ? 'admin/index.php' : 'user/dashboard.php');

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(post('email'));
    $pass  = post('password');

    $recent = count_where('login_attempts', "ip = ? AND success = 0 AND created_at > ?",
        array(client_ip(), date('Y-m-d H:i:s', strtotime('-15 minutes'))));

    if ($recent >= 6) {
        flash('error', 'Too many failed attempts. Wait 15 minutes and try again.');
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute(array($email));
        $u = $st->fetch();

        $ok = $u && password_verify($pass, $u['password']);
        db()->prepare('INSERT INTO login_attempts (email, ip, success, created_at) VALUES (?,?,?,?)')
            ->execute(array($email, client_ip(), $ok ? 1 : 0, now()));

        if (!$ok) {
            flash('error', 'That email and password do not match.');
        } elseif ($u['status'] === 'suspended') {
            flash('error', 'This account is suspended. Contact ' . setting('support_email') . '.');
        } elseif (setting('verify_email', '1') === '1' && (int)$u['email_verified'] === 0) {
            $_SESSION['resend_uid'] = $u['id'];
            flash('error', 'Confirm your email address first. We can send the link again.');
            redirect('verify.php?resend=1');
        } elseif ($u['status'] === 'pending') {
            flash('info', 'Your account is awaiting approval from the bank. We will email you the moment it opens.');
        } else {
            if ((int)$u['two_factor'] === 1) {
                $otp = make_otp();
                db()->prepare('UPDATE users SET otp_code = ?, otp_expires = ? WHERE id = ?')
                    ->execute(array($otp, date('Y-m-d H:i:s', strtotime('+10 minutes')), $u['id']));
                send_mail($u['email'], 'Your sign-in code', mail_template('Sign-in code',
                    '<p>Use this code to finish signing in. It expires in 10 minutes.</p>
                     <p style="font:600 30px/1 monospace;letter-spacing:.2em;color:#12352B">' . $otp . '</p>
                     <p>If this wasn\'t you, change your password immediately.</p>'));
                $_SESSION['otp_uid'] = $u['id'];
                redirect('verify.php?otp=1');
            }
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            $_SESSION['seen'] = time();
            db()->prepare('UPDATE users SET last_login = ?, last_ip = ? WHERE id = ?')
                ->execute(array(now(), client_ip(), $u['id']));
            audit('login', 'user', $u['id']);
            redirect($u['role'] === 'admin' ? 'admin/index.php' : 'user/dashboard.php');
        }
    }
}

layout_head('Sign in', 'auth');
auth_side();
?>
<div class="authmain">
  <div class="authcard">
    <?php auth_logo(); ?>
    <h1><?php echo te('login.welcome'); ?></h1>
    <p class="lead"><?php echo te('login.lead'); ?></p>
    <?php echo render_flash(); ?>

    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="field">
        <label for="email"><?php echo te('auth.email'); ?></label>
        <input type="email" id="email" name="email" value="<?php echo e($email); ?>" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label for="password"><?php echo te('auth.password'); ?></label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <span class="hint"><a href="<?php echo base_url('forgot.php'); ?>"><?php echo te('auth.forgot'); ?></a></span>
      </div>
      <button class="btn btn-primary btn-block" type="submit" data-busy-text="<?php echo te('login.checking'); ?>"><?php echo te('auth.signin_btn'); ?></button>
    </form>

    <p class="authfoot">
      <?php if (setting('registration_open', '1') === '1') { ?>
        <?php echo te('login.new_here'); ?> <a href="<?php echo base_url('register.php'); ?>"><?php echo te('auth.register'); ?></a>
      <?php } else { ?>
        <?php echo te('login.reg_closed'); ?> <?php echo e(setting('support_email')); ?>.
      <?php } ?>
    </p>
    <p class="authfoot"><a href="<?php echo base_url('admin/login.php'); ?>" class="muted"><?php echo te('login.staff'); ?></a></p>
  </div>
</div>
<?php layout_foot('auth');
