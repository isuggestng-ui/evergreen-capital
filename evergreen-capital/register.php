<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/authside.php';

if (auth_user()) redirect('user/dashboard.php');
if (setting('registration_open', '1') !== '1') {
    flash('error', t('reg.closed'));
    redirect('login.php');
}

$f = array('first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
           'country' => '', 'account_type' => 'Savings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($f as $k => $v) $f[$k] = post($k);
    $f['email'] = strtolower($f['email']);
    $pass  = post('password');
    $pass2 = post('password2');
    $errs  = array();

    if ($f['first_name'] === '' || $f['last_name'] === '') $errs[] = t('reg.err_name');
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errs[] = t('reg.err_email');
    if (strlen($pass) < 8) $errs[] = t('reg.err_pwlen');
    if ($pass !== $pass2) $errs[] = t('reg.err_pwmatch');
    if (empty($_POST['terms'])) $errs[] = t('reg.err_terms');

    $st = db()->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute(array($f['email']));
    if ($st->fetch()) $errs[] = t('reg.err_exists');

    if ($errs) {
        flash('error', implode(' ', $errs));
    } else {
        $verifyOn = setting('verify_email', '1') === '1';
        $autoOpen = setting('auto_activate', '0') === '1';
        $token = make_token(40);

        db()->prepare('INSERT INTO users
            (first_name,last_name,email,phone,password,role,status,account_number,account_type,currency,balance,email_verified,verify_token,country,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array(
                $f['first_name'], $f['last_name'], $f['email'], $f['phone'],
                password_hash($pass, PASSWORD_DEFAULT), 'user',
                $autoOpen ? 'active' : 'pending',
                make_account_number(), $f['account_type'], setting('currency_code', 'USD'),
                0, $verifyOn ? 0 : 1, $token, $f['country'], now()
            ));
        $uid = db()->lastInsertId();
        $user = find_user($uid);

        $bonus = (float)setting('welcome_bonus', '0');
        if ($bonus > 0) {
            post_transaction($uid, 'credit', 'adjustment', $bonus, 'Welcome bonus', array('channel' => 'system'));
        }

        if ($verifyOn) {
            $link = base_url('verify.php?token=' . $token);
            send_mail($f['email'], 'Confirm your email', mail_template('One step left',
                '<p>Hello ' . e($f['first_name']) . ',</p>
                 <p>Confirm this address to finish opening your account. Your account number is
                 <strong>' . e($user['account_number']) . '</strong>.</p>', 'Confirm email', $link));
            flash('success', 'Check your inbox — we sent a confirmation link to ' . $f['email'] . '.');
        } else {
            notify_user($user, 'Welcome to ' . setting('site_name'), 'Your account is open',
                '<p>Hello ' . e($f['first_name']) . ',</p><p>Your account number is <strong>' . e($user['account_number']) . '</strong>. Set a transfer PIN under Security before your first payment.</p>',
                'Sign in', base_url('login.php'));
            flash('success', 'Account created. You can sign in now.');
        }

        // Tell the bank someone new arrived.
        $admins = db()->query("SELECT email FROM users WHERE role='admin'")->fetchAll();
        foreach ($admins as $a) {
            send_mail($a['email'], 'New account: ' . user_name($user), mail_template('New registration',
                '<p><strong>' . e(user_name($user)) . '</strong> (' . e($user['email']) . ') just registered.</p>
                 <p>Account ' . e($user['account_number']) . ' · ' . e($user['account_type']) . '</p>',
                'Open in admin', base_url('admin/user.php?id=' . $uid)));
        }

        audit('register', 'user', $uid, $f['email']);
        redirect('login.php');
    }
}

layout_head('Open an account', 'auth');
auth_side('A bank account that opens today.', array(
    'No minimum balance, no monthly fee.',
    'Instant account number on approval.',
    'Loans and cards from the same dashboard.',
));
?>
<div class="authmain">
  <div class="authcard">
    <?php auth_logo(); ?>
    <h1><?php echo te('reg.title'); ?></h1>
    <p class="lead">Takes about a minute. You can add ID documents later.</p>
    <?php echo render_flash(); ?>

    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="row2">
        <div class="field"><label for="fn"><?php echo te('reg.first'); ?></label>
          <input type="text" id="fn" name="first_name" value="<?php echo e($f['first_name']); ?>" required autofocus></div>
        <div class="field"><label for="ln"><?php echo te('reg.last'); ?></label>
          <input type="text" id="ln" name="last_name" value="<?php echo e($f['last_name']); ?>" required></div>
      </div>
      <div class="field"><label for="em"><?php echo te('auth.email'); ?></label>
        <input type="email" id="em" name="email" value="<?php echo e($f['email']); ?>" autocomplete="email" required></div>
      <div class="row2">
        <div class="field"><label for="ph"><?php echo te('reg.phone'); ?></label>
          <input type="tel" id="ph" name="phone" value="<?php echo e($f['phone']); ?>"></div>
        <div class="field"><label for="co"><?php echo te('reg.country'); ?></label>
          <input type="text" id="co" name="country" value="<?php echo e($f['country']); ?>"></div>
      </div>
      <div class="field"><label for="at"><?php echo te('reg.acct_type'); ?></label>
        <select id="at" name="account_type">
          <?php foreach (array('Savings', 'Current', 'Fixed deposit', 'Business') as $t) {
            echo '<option' . ($f['account_type'] === $t ? ' selected' : '') . '>' . $t . '</option>';
          } ?>
        </select></div>
      <div class="row2">
        <div class="field"><label for="pw"><?php echo te('auth.password'); ?></label>
          <input type="password" id="pw" name="password" minlength="8" autocomplete="new-password" required></div>
        <div class="field"><label for="pw2"><?php echo te('reg.password2'); ?></label>
          <input type="password" id="pw2" name="password2" minlength="8" autocomplete="new-password" required></div>
      </div>
      <label class="check"><input type="checkbox" name="terms" value="1">
        <span><?php echo te('reg.terms'); ?></span></label>
      <button class="btn btn-primary btn-block" type="submit" data-busy-text="<?php echo te('reg.creating'); ?>"><?php echo te('reg.create'); ?></button>
    </form>

    <p class="authfoot"><?php echo te('reg.have'); ?> <a href="<?php echo base_url('login.php'); ?>"><?php echo te('auth.signin_btn'); ?></a></p>
  </div>
</div>
<?php layout_foot('auth');
