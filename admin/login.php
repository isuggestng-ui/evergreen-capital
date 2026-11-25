<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/authside.php';

if (is_admin()) redirect('admin/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(post('email'));
    $recent = count_where('login_attempts', 'ip = ? AND success = 0 AND created_at > ?',
        array(client_ip(), date('Y-m-d H:i:s', strtotime('-15 minutes'))));

    if ($recent >= 6) {
        flash('error', 'Too many failed attempts from this address. Wait 15 minutes.');
    } else {
        $st = db()->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $st->execute(array($email));
        $a = $st->fetch();
        $ok = $a && password_verify(post('password'), $a['password']);
        db()->prepare('INSERT INTO login_attempts (email, ip, success, created_at) VALUES (?,?,?,?)')
            ->execute(array($email, client_ip(), $ok ? 1 : 0, now()));

        if (!$ok) {
            flash('error', 'Those credentials do not match a staff account.');
        } elseif ($a['status'] === 'suspended') {
            flash('error', 'This staff account is suspended.');
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = $a['id'];
            $_SESSION['seen'] = time();
            db()->prepare('UPDATE users SET last_login = ?, last_ip = ? WHERE id = ?')
                ->execute(array(now(), client_ip(), $a['id']));
            audit('admin_login', 'user', $a['id']);
            redirect('admin/index.php');
        }
    }
}

layout_head('Staff sign-in', 'auth');
auth_side('The bank behind the bank.', array(
    'Approve or decline every movement of money.',
    'Credit, debit and backdate any account.',
    'Full audit trail of who did what.',
));
?>
<div class="authmain">
  <div class="authcard">
    <?php auth_logo(); ?>
    <span class="eyebrow">Administration</span>
    <h1>Staff sign-in</h1>
    <p class="lead">Restricted to bank personnel. Every action here is logged.</p>
    <?php echo render_flash(); ?>
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="field"><label for="email">Work email</label>
        <input type="email" id="email" name="email" required autofocus autocomplete="username"></div>
      <div class="field"><label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
    <p class="authfoot"><a href="<?php echo base_url('login.php'); ?>">Customer sign-in</a></p>
  </div>
</div>
<?php layout_foot('auth');
