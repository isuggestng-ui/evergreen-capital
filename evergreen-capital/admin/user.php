<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$id = (int)get('id', post('id'));
$u = find_user($id);
if (!$u) { flash('error', 'No customer with that id.'); redirect('admin/users.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    /* ---- credit, debit or set an exact balance ---- */
    if ($action === 'adjust') {
        $mode   = post('mode', 'credit');
        $amount = amount_in('amount');
        $desc   = post('description') !== '' ? post('description') : ($mode === 'credit' ? 'Account credit' : 'Account debit');
        $date   = post('date') !== '' ? date('Y-m-d H:i:s', strtotime(post('date'))) : null;
        $cat    = post('category', 'adjustment');
        $chan   = post('channel', 'branch');

        if ($mode === 'set') {
            $target = $amount;
            $diff = round($target - (float)$u['balance'], 2);
            if (abs($diff) < 0.01) {
                flash('info', 'The balance is already ' . money($target) . '.');
            } else {
                admin_adjust($u['id'], $diff > 0 ? 'credit' : 'debit', abs($diff), $desc, $date, $cat, $chan);
                flash('success', 'Balance set to ' . money($target) . '.');
            }
        } elseif ($amount <= 0) {
            flash('error', 'Enter an amount above zero.');
        } else {
            if ($mode === 'debit' && $amount > (float)$u['balance'] && post('allow_negative') !== '1') {
                flash('error', 'That debit exceeds the balance. Tick "allow overdraft" if you meant it.');
                redirect('admin/user.php?id=' . $id);
            }
            $r = admin_adjust($u['id'], $mode, $amount, $desc, $date, $cat, $chan);
            if (post('notify') === '1') {
                notify_user($u, ($mode === 'credit' ? 'Credit of ' : 'Debit of ') . money($amount),
                    $mode === 'credit' ? 'Your account was credited' : 'Your account was debited',
                    '<p>' . e($desc) . '</p>
                     <p><strong>' . ($mode === 'credit' ? '+' : '−') . money($amount) . '</strong><br>
                     Reference ' . e($r['ref']) . '<br>
                     Balance now ' . money($r['balance']) . '</p>',
                    'View receipt', base_url('user/receipt.php?ref=' . $r['ref']));
            }
            flash('success', ucfirst($mode) . ' of ' . money($amount) . ' posted. New balance ' . money($r['balance']) . '.');
        }
        redirect('admin/user.php?id=' . $id);
    }

    /* ---- edit the customer record ---- */
    if ($action === 'details') {
        db()->prepare('UPDATE users SET first_name=?, last_name=?, email=?, phone=?, account_type=?, currency=?,
                       address=?, city=?, state=?, country=?, zip=?, occupation=?, dob=?, updated_at=? WHERE id=?')
            ->execute(array(post('first_name'), post('last_name'), strtolower(post('email')), post('phone'),
                post('account_type'), post('currency'), post('address'), post('city'), post('state'),
                post('country'), post('zip'), post('occupation'), post('dob'), now(), $id));
        audit('user_updated', 'user', $id);
        flash('success', 'Customer details updated.');
        redirect('admin/user.php?id=' . $id);
    }

    /* ---- status, KYC, transfer lock ---- */
    if ($action === 'status') {
        $status = post('status');
        $kyc    = post('kyc_status');
        $lock   = post('transfer_locked') === '1' ? 1 : 0;
        db()->prepare('UPDATE users SET status=?, kyc_status=?, transfer_locked=?, lock_reason=?, updated_at=? WHERE id=?')
            ->execute(array($status, $kyc, $lock, post('lock_reason'), now(), $id));
        audit('user_status', 'user', $id, $status . ' / kyc ' . $kyc . ' / lock ' . $lock);

        if ($status !== $u['status'] || $kyc !== $u['kyc_status'] || $lock !== (int)$u['transfer_locked']) {
            $msg = '<p>Your account status is now <strong>' . e(ucfirst($status)) . '</strong>.</p>';
            if ($kyc === 'verified' && $u['kyc_status'] !== 'verified') $msg .= '<p>Your identity check passed. Full limits now apply.</p>';
            if ($lock === 1) $msg .= '<p>Transfers are on hold: ' . e(post('lock_reason')) . '</p>';
            notify_user($u, 'Account update', 'Account update', $msg);
        }
        flash('success', 'Account settings saved.');
        redirect('admin/user.php?id=' . $id);
    }

    /* ---- credentials ---- */
    if ($action === 'credentials') {
        $sets = array(); $args = array();
        if (post('password') !== '') {
            if (strlen(post('password')) < 8) { flash('error', 'Password needs 8 characters or more.'); redirect('admin/user.php?id=' . $id); }
            $sets[] = 'password = ?'; $args[] = password_hash(post('password'), PASSWORD_DEFAULT);
        }
        if (post('pin') !== '') {
            if (!preg_match('/^\d{4}$/', post('pin'))) { flash('error', 'The PIN must be four digits.'); redirect('admin/user.php?id=' . $id); }
            $sets[] = 'pin = ?'; $args[] = password_hash(post('pin'), PASSWORD_DEFAULT);
        }
        if (!$sets) { flash('info', 'Nothing to change.'); redirect('admin/user.php?id=' . $id); }
        $args[] = $id;
        db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
        audit('credentials_reset', 'user', $id);

        if (post('notify') === '1') {
            notify_user($u, 'Your sign-in details changed', 'Sign-in details changed',
                '<p>Your ' . (post('password') !== '' ? 'password' : '') .
                (post('password') !== '' && post('pin') !== '' ? ' and ' : '') .
                (post('pin') !== '' ? 'transfer PIN' : '') . ' was reset by the bank.</p>
                 <p>If you did not ask for this, call us straight away.</p>');
        }
        flash('success', 'Credentials updated.');
        redirect('admin/user.php?id=' . $id);
    }

    /* ---- transfer authorisation codes ---- */
    if ($action === 'codes') {
        foreach (explode(',', setting('code_sequence', 'cot,imf,tax')) as $type) {
            $type = trim($type);
            if ($type === '') continue;
            $val = trim(post('code_' . $type));
            $st = db()->prepare('SELECT id FROM transfer_codes WHERE user_id = ? AND code_type = ?');
            $st->execute(array($id, $type));
            $row = $st->fetch();
            if ($val === '') {
                if ($row) db()->prepare('DELETE FROM transfer_codes WHERE id = ?')->execute(array($row['id']));
            } elseif ($row) {
                db()->prepare('UPDATE transfer_codes SET code = ?, used = 0 WHERE id = ?')->execute(array($val, $row['id']));
            } else {
                db()->prepare('INSERT INTO transfer_codes (user_id, code_type, code, created_at) VALUES (?,?,?,?)')
                    ->execute(array($id, $type, $val, now()));
            }
        }
        audit('codes_set', 'user', $id);
        flash('success', 'Authorisation codes saved.');
        redirect('admin/user.php?id=' . $id);
    }

    /* ---- sign in as this customer ---- */
    if ($action === 'impersonate') {
        $_SESSION['impersonator'] = $admin['id'];
        $_SESSION['uid'] = $id;
        audit('impersonate', 'user', $id, 'admin viewed customer session');
        flash('info', 'You are viewing the app as ' . user_name($u) . '. Sign out to return to your own account.');
        redirect('user/dashboard.php');
    }

    /* ---- close the account ---- */
    if ($action === 'delete') {
        if ((int)$id === (int)$admin['id']) {
            flash('error', 'You cannot delete the account you are signed in with.');
            redirect('admin/user.php?id=' . $id);
        }
        foreach (array('transactions', 'transfers', 'deposits', 'withdrawals', 'loans',
                       'cards', 'beneficiaries', 'messages', 'kyc_documents', 'transfer_codes') as $tbl) {
            db()->prepare("DELETE FROM $tbl WHERE user_id = ?")->execute(array($id));
        }
        db()->prepare('DELETE FROM users WHERE id = ?')->execute(array($id));
        audit('user_deleted', 'user', $id, $u['email']);
        flash('success', 'Account and all its records deleted.');
        redirect('admin/users.php');
    }
}

/* ---- data for the page ---- */
$st = db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 15');
$st->execute(array($id));
$txs = $st->fetchAll();

$st = db()->prepare('SELECT * FROM transfer_codes WHERE user_id = ?');
$st->execute(array($id));
$codes = array();
foreach ($st->fetchAll() as $c) $codes[$c['code_type']] = $c['code'];

$stats = user_stats($id);
$openLoans = count_where('loans', "user_id = ? AND status = 'active'", array($id));

layout_head('Customer · ' . user_name($u), 'admin');
page_title(user_name($u), $u['email'] . ' · account ' . $u['account_number'],
    '<a class="btn" href="' . base_url('admin/users.php') . '">All customers</a>
     <form method="post" style="display:inline">' . csrf_field() . '
       <input type="hidden" name="action" value="impersonate">
       <input type="hidden" name="id" value="' . (int)$id . '">
       <button class="btn btn-brass" type="submit" data-confirm="Open the app as this customer?">View as customer</button>
     </form>');
?>

<div class="grid g4" style="margin-bottom:18px">
  <div class="stat"><span>Balance</span><b><?php echo money($u['balance']); ?></b>
    <small><?php echo e($u['account_type']); ?> · <?php echo e($u['currency']); ?></small></div>
  <div class="stat up"><span>Lifetime in</span><b><?php echo money($stats['in']); ?></b></div>
  <div class="stat down"><span>Lifetime out</span><b><?php echo money($stats['out']); ?></b></div>
  <div class="stat"><span>Loans outstanding</span><b><?php echo money($stats['loans']); ?></b>
    <small><?php echo $openLoans; ?> active</small></div>
</div>

<div class="grid g-main" style="align-items:start">
  <div class="stack">

    <section class="card">
      <span class="eyebrow">Post an entry</span>
      <h2>Credit or debit this account</h2>
      <p class="sub">Everything you post here lands in the customer's ledger with a receipt. Backdate it if you need the history to line up.</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="adjust">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <div class="row3">
          <div class="field"><label for="mode">What are you doing?</label>
            <select id="mode" name="mode">
              <option value="credit">Credit — add money</option>
              <option value="debit">Debit — take money</option>
              <option value="set">Set the balance to an exact figure</option>
            </select></div>
          <div class="field"><label for="amt">Amount</label>
            <div class="money-in"><span><?php echo e(cur()); ?></span>
              <input type="text" id="amt" name="amount" inputmode="decimal" placeholder="0.00" required></div></div>
          <div class="field"><label for="date">Date and time</label>
            <input type="datetime-local" id="date" name="date">
            <span class="hint">Leave blank for now.</span></div>
        </div>
        <div class="field"><label for="desc">Description on the statement</label>
          <input type="text" id="desc" name="description" placeholder="Salary credit — Northwind Ltd" maxlength="160"></div>
        <div class="row2">
          <div class="field"><label for="cat">Category</label>
            <select id="cat" name="category">
              <option value="adjustment">Adjustment</option>
              <option value="deposit">Deposit</option>
              <option value="transfer_in">Transfer in</option>
              <option value="transfer_out">Transfer out</option>
              <option value="withdrawal">Withdrawal</option>
              <option value="charge">Fee or charge</option>
              <option value="loan">Loan</option>
              <option value="repayment">Repayment</option>
            </select></div>
          <div class="field"><label for="chan">Channel</label>
            <select id="chan" name="channel">
              <option value="branch">Branch</option><option value="online">Online</option>
              <option value="ach">ACH</option><option value="wire">Wire</option>
              <option value="card">Card</option><option value="cheque">Cheque</option>
              <option value="system">System</option>
            </select></div>
        </div>
        <label class="check"><input type="checkbox" name="notify" value="1" checked>
          <span>Email the customer about this entry</span></label>
        <label class="check"><input type="checkbox" name="allow_negative" value="1">
          <span>Allow the balance to go negative</span></label>
        <button class="btn btn-primary" type="submit" data-confirm="Post this entry to the customer's ledger?">Post entry</button>
      </form>
    </section>

    <section class="card card-flush">
      <div class="card-head">
        <h2>Recent entries</h2>
        <a class="btn btn-sm" href="<?php echo base_url('admin/transactions.php?user=' . (int)$id); ?>">Full ledger</a>
      </div>
      <?php if (!$txs) { ?>
        <div class="empty"><h3>Nothing posted yet</h3><p>Use the form above to make the first entry.</p></div>
      <?php } else { ?>
      <div class="tablewrap"><table class="tbl">
        <thead><tr><th>Description</th><th>Date</th><th>Status</th><th class="right">Amount</th><th class="right">Balance</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($txs as $t) { ?>
          <tr>
            <td class="tdesc"><strong><?php echo e(str_short($t['description'], 40)); ?></strong>
              <small class="ref"><?php echo e($t['ref']); ?></small></td>
            <td class="small nowrap"><?php echo nice_date($t['created_at'], false); ?></td>
            <td><?php echo status_pill($t['status']); ?></td>
            <td class="num <?php echo $t['direction'] === 'credit' ? 'amt-credit' : 'amt-debit'; ?>">
              <?php echo ($t['direction'] === 'credit' ? '+' : '−') . money($t['amount']); ?></td>
            <td class="num muted"><?php echo money($t['balance_after']); ?></td>
            <td class="right"><a class="btn btn-sm" href="<?php echo base_url('admin/transactions.php?edit=' . (int)$t['id']); ?>">Edit</a></td>
          </tr>
        <?php } ?>
        </tbody></table></div>
      <?php } ?>
    </section>

    <section class="card">
      <span class="eyebrow">Record</span>
      <h2>Customer details</h2>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="details">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <div class="row3">
          <div class="field"><label>First name</label><input type="text" name="first_name" value="<?php echo e($u['first_name']); ?>"></div>
          <div class="field"><label>Last name</label><input type="text" name="last_name" value="<?php echo e($u['last_name']); ?>"></div>
          <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?php echo e($u['phone']); ?>"></div>
        </div>
        <div class="row3">
          <div class="field"><label>Email</label><input type="email" name="email" value="<?php echo e($u['email']); ?>"></div>
          <div class="field"><label>Account type</label>
            <select name="account_type">
              <?php foreach (array('Savings', 'Current', 'Fixed deposit', 'Business', 'Administration') as $t)
                echo '<option' . ($u['account_type'] === $t ? ' selected' : '') . '>' . $t . '</option>'; ?>
            </select></div>
          <div class="field"><label>Currency</label><input type="text" name="currency" value="<?php echo e($u['currency']); ?>" maxlength="4"></div>
        </div>
        <div class="field"><label>Address</label><input type="text" name="address" value="<?php echo e($u['address']); ?>"></div>
        <div class="row3">
          <div class="field"><label>City</label><input type="text" name="city" value="<?php echo e($u['city']); ?>"></div>
          <div class="field"><label>State</label><input type="text" name="state" value="<?php echo e($u['state']); ?>"></div>
          <div class="field"><label>Postal code</label><input type="text" name="zip" value="<?php echo e($u['zip']); ?>"></div>
        </div>
        <div class="row3">
          <div class="field"><label>Country</label><input type="text" name="country" value="<?php echo e($u['country']); ?>"></div>
          <div class="field"><label>Occupation</label><input type="text" name="occupation" value="<?php echo e($u['occupation']); ?>"></div>
          <div class="field"><label>Date of birth</label><input type="date" name="dob" value="<?php echo e($u['dob']); ?>"></div>
        </div>
        <button class="btn btn-primary" type="submit">Save details</button>
      </form>
    </section>
  </div>

  <div class="stack">
    <section class="card">
      <span class="eyebrow">Controls</span>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <div class="field"><label for="st">Account status</label>
          <select id="st" name="status">
            <?php foreach (array('active', 'pending', 'suspended') as $s)
              echo '<option value="' . $s . '"' . ($u['status'] === $s ? ' selected' : '') . '>' . ucfirst($s) . '</option>'; ?>
          </select></div>
        <div class="field"><label for="kyc">Identity check</label>
          <select id="kyc" name="kyc_status">
            <?php foreach (array('unverified', 'pending', 'verified', 'declined') as $s)
              echo '<option value="' . $s . '"' . ($u['kyc_status'] === $s ? ' selected' : '') . '>' . ucfirst($s) . '</option>'; ?>
          </select></div>
        <label class="check"><input type="checkbox" name="transfer_locked" value="1" <?php echo (int)$u['transfer_locked'] === 1 ? 'checked' : ''; ?>>
          <span>Hold all outgoing transfers</span></label>
        <div class="field"><label for="lr">Reason shown to the customer</label>
          <input type="text" id="lr" name="lock_reason" value="<?php echo e($u['lock_reason']); ?>"
            placeholder="Pending compliance review"></div>
        <button class="btn btn-primary btn-block" type="submit">Save controls</button>
      </form>
    </section>

    <?php if (setting('enable_codes', '0') === '1') { ?>
    <section class="card">
      <span class="eyebrow">Authorisation codes</span>
      <h2>Transfer codes</h2>
      <p class="sub">The customer must enter these, in order, to release a transfer. Leave a field empty to drop that step for this customer.</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="codes">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <?php foreach (explode(',', setting('code_sequence', 'cot,imf,tax')) as $type) {
          $type = trim($type); if ($type === '') continue; ?>
          <div class="field">
            <label for="c<?php echo e($type); ?>"><?php echo e(strtoupper($type)); ?> code</label>
            <input type="text" id="c<?php echo e($type); ?>" name="code_<?php echo e($type); ?>"
                   value="<?php echo e(isset($codes[$type]) ? $codes[$type] : ''); ?>" class="mono">
          </div>
        <?php } ?>
        <button class="btn btn-primary btn-block" type="submit">Save codes</button>
      </form>
    </section>
    <?php } ?>

    <section class="card">
      <span class="eyebrow">Access</span>
      <h2>Reset credentials</h2>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="credentials">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <div class="field"><label>New password</label><input type="text" name="password" placeholder="Leave blank to keep"></div>
        <div class="field"><label>New transfer PIN</label><input type="text" name="pin" maxlength="4" placeholder="Leave blank to keep"></div>
        <label class="check"><input type="checkbox" name="notify" value="1" checked><span>Tell the customer by email</span></label>
        <button class="btn btn-primary btn-block" type="submit">Update credentials</button>
      </form>
    </section>

    <section class="card">
      <span class="eyebrow">Snapshot</span>
      <dl class="kv">
        <dt>Account number</dt><dd><?php echo e($u['account_number']); ?></dd>
        <dt>Registered</dt><dd><?php echo nice_date($u['created_at'], false); ?></dd>
        <dt>Last sign-in</dt><dd><?php echo nice_date($u['last_login']); ?></dd>
        <dt>Last IP</dt><dd><?php echo e($u['last_ip'] ? $u['last_ip'] : '—'); ?></dd>
        <dt>Email confirmed</dt><dd><?php echo (int)$u['email_verified'] === 1 ? 'Yes' : 'No'; ?></dd>
        <dt>Two-step sign-in</dt><dd><?php echo (int)$u['two_factor'] === 1 ? 'On' : 'Off'; ?></dd>
        <dt>Transfer PIN</dt><dd><?php echo $u['pin'] ? 'Set' : 'Not set'; ?></dd>
      </dl>
    </section>

    <section class="card">
      <span class="eyebrow">Danger zone</span>
      <h2>Close this account</h2>
      <p class="sub">Deletes the customer and every transaction, loan, card and message attached to them. There is no undo.</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <button class="btn btn-danger btn-block" type="submit"
          data-confirm="Permanently delete <?php echo e(user_name($u)); ?> and all their records?">Delete permanently</button>
      </form>
    </section>
  </div>
</div>

<?php layout_foot('admin');
