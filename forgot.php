<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/authside.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(post('email'));
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute(array($email));
    $u = $st->fetch();
    if ($u) {
        $token = make_token(48);
        db()->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
            ->execute(array($token, date('Y-m-d H:i:s', strtotime('+1 hour')), $u['id']));
        send_mail($u['email'], 'Reset your password', mail_template('Reset your password',
            '<p>Hello ' . e($u['first_name']) . ',</p>
             <p>Use the button below to choose a new password. The link works once and expires in an hour.</p>
             <p>Didn\'t ask for this? Ignore this email — nothing changes.</p>',
            'Choose a new password', base_url('reset.php?token=' . $token)));
        audit('password_reset_requested', 'user', $u['id']);
    }
    flash('success', 'If that address has an account, a reset link is on its way.');
    redirect('login.php');
}

layout_head('Reset password', 'auth');
auth_side('Locked out? It happens.', array(
    'Reset links expire after one hour.',
    'We never email your old password.',
    'Your balance and history are untouched.',
));
?>
<div class="authmain">
  <div class="authcard">
    <?php auth_logo(); ?>
    <h1>Reset your password</h1>
    <p class="lead">Tell us the email on the account and we will send a link.</p>
    <?php echo render_flash(); ?>
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="field"><label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus></div>
      <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
    </form>
    <p class="authfoot"><a href="<?php echo base_url('login.php'); ?>">Back to sign in</a></p>
  </div>
</div>
<?php layout_foot('auth');
