<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

$methods = array(
    'Bank transfer' => 'Move money from another bank using the details below, then log it here.',
    'Wire transfer' => 'International wire. Allow one to three working days.',
    'Cash deposit'  => 'Paid in at a branch or agent. Attach the teller slip.',
    'Cheque'        => 'Photograph both sides of the cheque.',
    'Crypto'        => 'Send to the wallet your account officer issued, then attach the hash.',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $amount = amount_in('amount');
    $method = post('method', 'Bank transfer');
    $note   = post('note');
    $min    = (float)setting('min_deposit', '0');

    if ($amount < $min) {
        flash('error', 'The smallest deposit we can log is ' . money($min) . '.');
    } else {
        $proof = upload_file('proof', 'proofs');
        $ref = make_ref('DEP');
        $needsApproval = setting('approve_deposits', '1') === '1';
        $status = $needsApproval ? 'pending' : 'approved';

        db()->prepare('INSERT INTO deposits (user_id, ref, amount, method, proof, note, status, created_at)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute(array($u['id'], $ref, $amount, $method, $proof, $note, $status, now()));

        post_transaction($u['id'], 'credit', 'deposit', $amount, 'Deposit — ' . $method,
            array('ref' => $ref, 'channel' => strtolower($method), 'status' => $needsApproval ? 'pending' : 'completed'));

        notify_user($u, 'Deposit logged — ' . money($amount), 'Deposit logged',
            '<p>Reference <strong>' . e($ref) . '</strong> for ' . money($amount) . ' by ' . e($method) . '.</p>
             <p>' . ($needsApproval ? 'We will confirm it against our records and credit your account shortly.'
                                    : 'Your account has been credited.') . '</p>',
            'View receipt', base_url('user/receipt.php?ref=' . $ref));

        foreach (db()->query("SELECT email FROM users WHERE role='admin'")->fetchAll() as $a) {
            send_mail($a['email'], 'Deposit to confirm — ' . money($amount), mail_template('Deposit logged',
                '<p><strong>' . e(user_name($u)) . '</strong> logged ' . money($amount) . ' by ' . e($method) . '.</p>
                 <p>Reference ' . e($ref) . '</p>', 'Open approvals', base_url('admin/requests.php?tab=deposits')));
        }

        audit('deposit_created', 'deposit', 0, $ref . ' ' . money($amount));
        flash('success', $needsApproval
            ? 'Deposit logged. It shows as pending until the bank confirms it.'
            : 'Deposit credited to your account.');
        redirect('user/receipt.php?ref=' . $ref);
    }
}

$st = db()->prepare('SELECT * FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT 10');
$st->execute(array($u['id']));
$rows = $st->fetchAll();

layout_head('Add funds', 'user');
page_title('Add funds', 'Send money in from anywhere, then log it here so we can match it.');
?>

<div class="grid g-main" style="align-items:start">
  <section class="card">
    <h2>Log a deposit</h2>
    <p class="sub">Attach proof and we will clear it faster.</p>
    <form method="post" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="field">
        <label for="amount">Amount</label>
        <div class="money-in"><span><?php echo e(cur()); ?></span>
          <input type="text" id="amount" name="amount" inputmode="decimal" placeholder="0.00" required></div>
        <span class="hint">Minimum <?php echo money(setting('min_deposit', '0')); ?>.</span>
      </div>
      <div class="field">
        <label for="method">How did you send it?</label>
        <select id="method" name="method">
          <?php foreach ($methods as $m => $desc) echo '<option value="' . e($m) . '">' . e($m) . '</option>'; ?>
        </select>
      </div>
      <div class="field">
        <label for="proof">Proof of payment <span class="muted">(optional)</span></label>
        <input type="file" id="proof" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf">
        <span class="hint">Screenshot, teller slip or PDF. Up to 5 MB.</span>
      </div>
      <div class="field">
        <label for="note">Anything we should know?</label>
        <textarea id="note" name="note" rows="3" placeholder="Sent from Chase ••4410 at 9:15am"></textarea>
      </div>
      <button class="btn btn-primary btn-block" type="submit" data-busy-text="Logging…">Log this deposit</button>
    </form>
  </section>

  <div class="stack">
    <section class="card">
      <span class="eyebrow">Pay into</span>
      <h2><?php echo e(setting('site_legal', setting('site_name'))); ?></h2>
      <p class="sub">Use these details at your other bank. Always quote your account number as the reference.</p>
      <dl class="kv">
        <dt>Account name</dt><dd><?php echo e(user_name($u)); ?></dd>
        <dt>Account number</dt><dd><?php echo e($u['account_number']); ?>
          <a href="#" class="small" data-copy="<?php echo e($u['account_number']); ?>">Copy</a></dd>
        <dt>Routing</dt><dd><?php echo e(setting('routing_number', '—')); ?></dd>
        <dt>SWIFT</dt><dd><?php echo e(setting('swift_code', '—')); ?></dd>
        <dt>Bank address</dt><dd style="font-family:var(--sans);text-align:right"><?php echo e(setting('address', '—')); ?></dd>
      </dl>
    </section>

    <section class="card card-flush">
      <div class="card-head"><h2>Recent deposits</h2></div>
      <?php if (!$rows) { ?>
        <div class="empty"><h3>No deposits yet</h3><p>Anything you log will appear here with its status.</p></div>
      <?php } else { ?>
        <div class="tablewrap"><table class="tbl">
          <thead><tr><th>Reference</th><th>Method</th><th>Status</th><th class="right">Amount</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r) { ?>
            <tr>
              <td><a class="ref" href="<?php echo base_url('user/receipt.php?ref=' . urlencode($r['ref'])); ?>"><?php echo e($r['ref']); ?></a><br>
                <small class="muted"><?php echo nice_date($r['created_at'], false); ?></small></td>
              <td class="small"><?php echo e($r['method']); ?></td>
              <td><?php echo status_pill($r['status']); ?></td>
              <td class="num"><?php echo money($r['amount']); ?></td>
            </tr>
          <?php } ?>
          </tbody></table></div>
      <?php } ?>
    </section>
  </div>
</div>

<?php layout_foot('user');
