<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'create') {
    csrf_check();
    $email = strtolower(post('email'));
    $pass  = post('password');
    $errs = array();
    if (post('first_name') === '' || post('last_name') === '') $errs[] = 'Enter a full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Enter a valid email.';
    if (strlen($pass) < 8) $errs[] = 'Password needs at least 8 characters.';
    $st = db()->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute(array($email));
    if ($st->fetch()) $errs[] = 'That email already has an account.';

    if ($errs) {
        flash('error', implode(' ', $errs));
    } else {
        $opening = amount_in('opening');
        db()->prepare('INSERT INTO users
            (first_name,last_name,email,phone,password,role,status,account_number,account_type,currency,balance,email_verified,kyc_status,country,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array(post('first_name'), post('last_name'), $email, post('phone'),
                password_hash($pass, PASSWORD_DEFAULT), post('role', 'user'), 'active',
                make_account_number(), post('account_type', 'Savings'), setting('currency_code', 'USD'),
                0, 1, 'unverified', post('country'), now()));
        $uid = db()->lastInsertId();
        $new = find_user($uid);

        if ($opening > 0) {
            admin_adjust($uid, 'credit', $opening, 'Opening deposit', null, 'deposit', 'branch');
        }

        if (post('notify') === '1') {
            notify_user($new, 'Your account is open', 'Welcome to ' . setting('site_name'),
                '<p>Hello ' . e(post('first_name')) . ',</p>
                 <p>We opened your account. Your number is <strong>' . e($new['account_number']) . '</strong>.</p>
                 <p>Sign in with ' . e($email) . ' and the password given to you, then change it under Security.</p>' .
                 ($opening > 0 ? '<p>Opening balance: <strong>' . money($opening) . '</strong></p>' : ''),
                'Sign in', base_url('login.php'));
        }

        audit('user_created', 'user', $uid, $email);
        flash('success', 'Account opened for ' . user_name($new) . ' — ' . $new['account_number'] . '.');
        redirect('admin/user.php?id=' . $uid);
    }
}

$q      = get('q');
$status = get('status');
$sort   = get('sort', 'new');
$page   = max(1, (int)get('p', 1));
$per    = 20;

$where = "role = 'user'";
$args  = array();
if (get('role') === 'admin') { $where = "role = 'admin'"; }
if ($q !== '') {
    $where .= " AND (first_name " . db_like() . " ? OR last_name " . db_like() . " ? OR email " . db_like() . " ? OR account_number " . db_like() . " ? OR phone " . db_like() . " ?)";
    for ($i = 0; $i < 5; $i++) $args[] = '%' . $q . '%';
}
if ($status !== '') { $where .= ' AND status = ?'; $args[] = $status; }

$order = 'id DESC';
if ($sort === 'balance') $order = 'balance DESC';
if ($sort === 'name') $order = 'first_name ASC';

$total  = count_where('users', $where, $args);
$pages  = max(1, (int)ceil($total / $per));
$page   = min($page, $pages);
$offset = ($page - 1) * $per;

$st = db()->prepare("SELECT * FROM users WHERE $where ORDER BY $order LIMIT $per OFFSET $offset");
$st->execute($args);
$rows = $st->fetchAll();

layout_head('Customers', 'admin');
page_title('Customers', $total . ' account' . ($total === 1 ? '' : 's') . ' on the books.',
    '<a class="btn btn-primary" href="#open">Open an account</a>');
?>

<section class="card" style="margin-bottom:18px">
  <form method="get" class="flexbar" style="align-items:end;gap:12px">
    <div class="field mb0" style="flex:2;min-width:200px"><label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?php echo e($q); ?>" placeholder="Name, email, account number, phone"></div>
    <div class="field mb0" style="min-width:150px"><label for="status">Status</label>
      <select id="status" name="status">
        <option value="">Any</option>
        <?php foreach (array('active', 'pending', 'suspended') as $s)
          echo '<option value="' . $s . '"' . ($status === $s ? ' selected' : '') . '>' . ucfirst($s) . '</option>'; ?>
      </select></div>
    <div class="field mb0" style="min-width:150px"><label for="sort">Sort by</label>
      <select id="sort" name="sort">
        <option value="new"<?php echo $sort === 'new' ? ' selected' : ''; ?>>Newest first</option>
        <option value="balance"<?php echo $sort === 'balance' ? ' selected' : ''; ?>>Largest balance</option>
        <option value="name"<?php echo $sort === 'name' ? ' selected' : ''; ?>>Name</option>
      </select></div>
    <button class="btn btn-primary" type="submit">Search</button>
    <a class="btn" href="<?php echo base_url('admin/users.php?role=admin'); ?>">Staff accounts</a>
  </form>
</section>

<section class="card card-flush" style="margin-bottom:18px">
  <?php if (!$rows) { ?>
    <div class="empty"><h3>No accounts match</h3><p>Try a different search or clear the filters.</p>
      <a class="btn" href="<?php echo base_url('admin/users.php'); ?>">Clear</a></div>
  <?php } else { ?>
  <div class="tablewrap"><table class="tbl">
    <thead><tr><th>Customer</th><th>Account</th><th>Status</th><th>KYC</th><th class="right">Balance</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r) { ?>
      <tr>
        <td>
          <div class="flexbar" style="gap:10px">
            <span class="avatar" style="background:var(--line-2);color:var(--ink);border-color:var(--line)">
              <?php echo e(initials($r['first_name'], $r['last_name'])); ?></span>
            <span><strong><?php echo e(user_name($r)); ?></strong><br>
              <small class="muted"><?php echo e($r['email']); ?></small></span>
          </div>
        </td>
        <td class="mono small"><?php echo e($r['account_number']); ?><br>
          <span class="muted"><?php echo e($r['account_type']); ?></span></td>
        <td><?php echo status_pill($r['status']); ?>
          <?php if ((int)$r['transfer_locked'] === 1) echo '<br><span class="pill pill-bad" style="margin-top:4px">Locked</span>'; ?></td>
        <td><?php echo status_pill($r['kyc_status']); ?></td>
        <td class="num"><?php echo money($r['balance']); ?></td>
        <td class="small muted nowrap"><?php echo nice_date($r['created_at'], false); ?></td>
        <td class="right"><a class="btn btn-sm btn-primary" href="<?php echo base_url('admin/user.php?id=' . (int)$r['id']); ?>">Manage</a></td>
      </tr>
    <?php } ?>
    </tbody></table></div>
  <?php if ($pages > 1) { ?>
    <div class="flexbar" style="padding:14px 18px;border-top:1px solid var(--line-2)">
      <span class="small muted">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
      <div class="spacer"></div>
      <?php $b = array('q' => $q, 'status' => $status, 'sort' => $sort);
      if ($page > 1) echo '<a class="btn btn-sm" href="?' . e(http_build_query(array_merge($b, array('p' => $page - 1)))) . '">Previous</a> ';
      if ($page < $pages) echo '<a class="btn btn-sm" href="?' . e(http_build_query(array_merge($b, array('p' => $page + 1)))) . '">Next</a>'; ?>
    </div>
  <?php } ?>
  <?php } ?>
</section>

<section class="card" id="open">
  <span class="eyebrow">New account</span>
  <h2>Open an account</h2>
  <p class="sub">Creates an active account straight away, with an optional opening balance.</p>
  <form method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="create">
    <div class="row3">
      <div class="field"><label>First name</label><input type="text" name="first_name" required></div>
      <div class="field"><label>Last name</label><input type="text" name="last_name" required></div>
      <div class="field"><label>Phone</label><input type="tel" name="phone"></div>
    </div>
    <div class="row3">
      <div class="field"><label>Email</label><input type="email" name="email" required></div>
      <div class="field"><label>Temporary password</label><input type="text" name="password" minlength="8" required
        value="<?php echo e(substr(str_shuffle('ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 10)); ?>"></div>
      <div class="field"><label>Country</label><input type="text" name="country"></div>
    </div>
    <div class="row3">
      <div class="field"><label>Account type</label>
        <select name="account_type"><option>Savings</option><option>Current</option><option>Fixed deposit</option><option>Business</option></select></div>
      <div class="field"><label>Role</label>
        <select name="role"><option value="user">Customer</option><option value="admin">Administrator</option></select></div>
      <div class="field"><label>Opening balance</label>
        <div class="money-in"><span><?php echo e(cur()); ?></span>
          <input type="text" name="opening" inputmode="decimal" value="0"></div></div>
    </div>
    <label class="check"><input type="checkbox" name="notify" value="1" checked>
      <span>Email the customer their account number and sign-in details.</span></label>
    <button class="btn btn-primary" type="submit">Open the account</button>
  </form>
</section>

<?php layout_foot('admin');
