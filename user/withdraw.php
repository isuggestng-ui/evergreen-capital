<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $amount = amount_in('amount');
    $method = post('method', 'Bank account');
    $fee    = transfer_fee($amount);
    $dest   = trim(post('bank_name') . ' · ' . post('account_number') . ' · ' . post('account_name'));

    $errs = array();
    if ($amount <= 0) $errs[] = 'Enter an amount above zero.';
    $available = available_balance($u['id']);
    if ($amount + $fee > $available) $errs[] = 'Insufficient funds. That costs ' . money($amount + $fee)
        . ' with the fee and you have ' . money($available) . ' available.';
    if (post('account_number') === '') $errs[] = 'Enter the destination account number.';
    if (setting('require_pin', '1') === '1') {
        if (!$u['pin']) { flash('error', 'Set a transfer PIN under Security first.'); redirect('user/security.php'); }
        if (!password_verify(post('pin'), $u['pin'])) $errs[] = 'That PIN is not right.';
    }

    if ($errs) {
        flash('error', implode(' ', $errs));
    } else {
        $ref = make_ref('WDL');
        $hold = setting('approve_withdrawals', '1') === '1';
        db()->prepare('INSERT INTO withdrawals (user_id, ref, amount, fee, method, destination, status, created_at)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute(array($u['id'], $ref, $amount, $fee, $method, $dest, $hold ? 'pending' : 'approved', now()));

        post_transaction($u['id'], 'debit', 'withdrawal', $amount, 'Withdrawal to ' . $dest,
            array('ref' => $ref, 'fee' => $fee, 'channel' => strtolower($method),
                  'status' => $hold ? 'pending' : 'completed', 'counterparty' => $dest));

        notify_user($u, 'Withdrawal request — ' . money($amount), 'Withdrawal requested',
            '<p>Reference <strong>' . e($ref) . '</strong></p>
             <p>' . money($amount) . ' to ' . e($dest) . '. Fee ' . money($fee) . '.</p>
             <p>' . ($hold ? 'The funds are on hold while we review the request.' : 'The payout is on its way.') . '</p>',
            'View receipt', base_url('user/receipt.php?ref=' . $ref));

        foreach (db()->query("SELECT email FROM users WHERE role='admin'")->fetchAll() as $a) {
            send_mail($a['email'], 'Withdrawal to review — ' . money($amount), mail_template('Withdrawal requested',
                '<p><strong>' . e(user_name($u)) . '</strong> asked to withdraw ' . money($amount) . '.</p>
                 <p>To: ' . e($dest) . '<br>Reference ' . e($ref) . '</p>',
                'Open approvals', base_url('admin/requests.php?tab=withdrawals')));
        }

        flash('success', $hold ? 'Withdrawal submitted for approval.' : 'Withdrawal approved and on its way.');
        redirect('user/receipt.php?ref=' . $ref);
    }
}

$st = db()->prepare('SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 10');
$st->execute(array($u['id']));
$rows = $st->fetchAll();

layout_head('Withdraw', 'user');
page_title('Withdraw funds', 'Send money out to an account you own.');
?>

<div class="grid g-main" style="align-items:start">
  <section class="card">
    <h2>Request a payout</h2>
    <?php $avail = available_balance($u['id']); $held = held_amount($u['id']); ?>
    <p class="sub">Available to withdraw: <strong><?php echo money($avail); ?></strong>
      <?php if ($held > 0) echo '<br><span class="muted">' . money($held) . ' of your ' . money($u['balance']) . ' balance is held against payments still clearing.</span>'; ?></p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="field">
        <label for="amount">Amount</label>
        <div class="money-in"><span><?php echo e(cur()); ?></span>
          <input type="text" id="amount" name="amount" inputmode="decimal" placeholder="0.00" required></div>
        <span class="hint">Fee <?php echo e(setting('fee_percent', '0')); ?>% is taken on top.</span>
      </div>
      <div class="field">
        <label for="method">Payout method</label>
        <select id="method" name="method">
          <option>Bank account</option>
          <option>Wire transfer</option>
          <option>Cash pickup</option>
          <option>Crypto wallet</option>
        </select>
      </div>
      <div class="field"><label>Account name</label><input type="text" name="account_name" value="<?php echo e(user_name($u)); ?>"></div>
      <div class="row2">
        <div class="field"><label>Account number / wallet</label><input type="text" name="account_number" required></div>
        <div class="field"><label>Bank or network</label><input type="text" name="bank_name"></div>
      </div>
      <?php if (setting('require_pin', '1') === '1') { ?>
      <div class="field pinbox">
        <label for="pin">Transfer PIN</label>
        <input type="password" id="pin" name="pin" maxlength="4" inputmode="numeric" required>
      </div>
      <?php } ?>
      <button class="btn btn-primary btn-block" type="submit" data-busy-text="Submitting…">Request withdrawal</button>
    </form>
  </section>

  <section class="card card-flush">
    <div class="card-head"><h2>Recent withdrawals</h2></div>
    <?php if (!$rows) { ?>
      <div class="empty"><h3>Nothing withdrawn yet</h3><p>Payout requests and their status will show up here.</p></div>
    <?php } else { ?>
      <div class="tablewrap"><table class="tbl">
        <thead><tr><th>Reference</th><th>Destination</th><th>Status</th><th class="right">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) { ?>
          <tr>
            <td><a class="ref" href="<?php echo base_url('user/receipt.php?ref=' . urlencode($r['ref'])); ?>"><?php echo e($r['ref']); ?></a><br>
              <small class="muted"><?php echo nice_date($r['created_at'], false); ?></small></td>
            <td class="small"><?php echo e(str_short($r['destination'], 34)); ?></td>
            <td><?php echo status_pill($r['status']); ?></td>
            <td class="num"><?php echo money($r['amount']); ?></td>
          </tr>
        <?php } ?>
        </tbody></table></div>
    <?php } ?>
  </section>
</div>

<?php layout_foot('user');
