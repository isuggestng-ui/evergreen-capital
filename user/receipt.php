<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_user();

$ref = get('ref');
$st = db()->prepare('SELECT * FROM transactions WHERE ref = ? ORDER BY id ASC');
$st->execute(array($ref));
$rows = $st->fetchAll();

// A customer sees only their own leg of a transaction; an admin sees any.
$t = null;
foreach ($rows as $r) {
    if ((int)$r['user_id'] === (int)$me['id']) { $t = $r; break; }
}
if (!$t && $me['role'] === 'admin' && $rows) $t = $rows[0];

if (!$t) {
    flash('error', 'No receipt found for that reference.');
    redirect($me['role'] === 'admin' ? 'admin/transactions.php' : 'user/transactions.php');
}

$owner = find_user($t['user_id']);

$st = db()->prepare('SELECT * FROM transfers WHERE ref = ?');
$st->execute(array($ref));
$transfer = $st->fetch();

$stampClass = $t['status'] === 'completed' ? '' : ($t['status'] === 'pending' ? 'wait' : 'bad');
$stampText  = $t['status'] === 'completed' ? 'Completed' : ($t['status'] === 'pending' ? 'In progress' : ucfirst($t['status']));

$ctx  = $me['role'] === 'admin' ? 'admin' : 'user';
$back = $ctx === 'admin'
    ? base_url('admin/transactions.php?user=' . (int)$t['user_id'])
    : base_url('user/transactions.php');

layout_head('Receipt ' . $ref, $ctx);
page_title('Payment advice', 'Reference ' . $ref,
    '<button class="btn btn-primary noprint" onclick="window.print()">Print or save as PDF</button>
     <a class="btn noprint" href="' . $back . '">Back to the ledger</a>');
?>

<div class="sheet">
  <div class="sheet-head">
    <div>
      <div class="m"><?php echo e(setting('site_name')); ?></div>
      <small><?php echo e(setting('site_legal', '')); ?><br><?php echo e(setting('address', '')); ?></small>
    </div>
    <div class="right">
      <span class="stamp <?php echo $stampClass; ?>"><?php echo e($stampText); ?></span>
      <small style="display:block;margin-top:10px;color:var(--muted)">Issued <?php echo nice_date(now()); ?></small>
    </div>
  </div>

  <div style="display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;margin-bottom:24px">
    <div>
      <span class="eyebrow">Account holder</span>
      <strong style="display:block;font-size:16px"><?php echo e(user_name($owner)); ?></strong>
      <span class="mono small muted"><?php echo e($owner['account_number']); ?> · <?php echo e($owner['account_type']); ?></span>
    </div>
    <div class="right">
      <span class="eyebrow"><?php echo $t['direction'] === 'credit' ? 'Amount received' : 'Amount sent'; ?></span>
      <strong style="display:block;font:400 32px/1 var(--serif);color:<?php echo $t['direction'] === 'credit' ? 'var(--credit)' : 'var(--ink-2)'; ?>">
        <?php echo ($t['direction'] === 'credit' ? '+' : '−') . money($t['amount']); ?>
      </strong>
    </div>
  </div>

  <table>
    <tr><td>Reference</td><td><?php echo e($t['ref']); ?></td></tr>
    <tr><td>Description</td><td style="font-family:var(--sans)"><?php echo e($t['description']); ?></td></tr>
    <tr><td>Type</td><td><?php echo e(ucfirst(str_replace('_', ' ', $t['category']))); ?></td></tr>
    <tr><td>Channel</td><td><?php echo e(ucfirst($t['channel'])); ?></td></tr>
    <?php if ($t['counterparty']) { ?><tr><td>Counterparty</td><td><?php echo e($t['counterparty']); ?></td></tr><?php } ?>
    <?php if ((float)$t['fee'] > 0) { ?><tr><td>Fee</td><td><?php echo money($t['fee']); ?></td></tr><?php } ?>
    <tr><td>Date and time (UTC)</td><td><?php echo nice_date($t['created_at']); ?></td></tr>
    <tr><td>Status</td><td><?php echo e(ucfirst($t['status'])); ?></td></tr>
    <?php if ($t['status'] === 'completed') { ?>
      <tr><td>Balance after</td><td><?php echo money($t['balance_after']); ?></td></tr>
    <?php } ?>
  </table>

  <?php if ($transfer) { ?>
    <h3 style="margin:26px 0 10px;font:400 19px/1.2 var(--serif)">Beneficiary</h3>
    <table>
      <tr><td>Name</td><td><?php echo e($transfer['beneficiary_name']); ?></td></tr>
      <tr><td>Account</td><td><?php echo e($transfer['beneficiary_account']); ?></td></tr>
      <tr><td>Bank</td><td><?php echo e($transfer['bank_name']); ?></td></tr>
      <?php if ($transfer['swift']) { ?><tr><td>SWIFT / BIC</td><td><?php echo e($transfer['swift']); ?></td></tr><?php } ?>
      <?php if ($transfer['iban']) { ?><tr><td>IBAN</td><td><?php echo e($transfer['iban']); ?></td></tr><?php } ?>
      <?php if ($transfer['routing']) { ?><tr><td>Routing</td><td><?php echo e($transfer['routing']); ?></td></tr><?php } ?>
      <?php if ($transfer['country']) { ?><tr><td>Country</td><td><?php echo e($transfer['country']); ?></td></tr><?php } ?>
      <?php if ($transfer['narration']) { ?><tr><td>Narration</td><td style="font-family:var(--sans)"><?php echo e($transfer['narration']); ?></td></tr><?php } ?>
      <?php if ($transfer['admin_note']) { ?><tr><td>Bank note</td><td style="font-family:var(--sans)"><?php echo e($transfer['admin_note']); ?></td></tr><?php } ?>
    </table>
  <?php } ?>

  <p class="small muted" style="margin-top:26px;padding-top:16px;border-top:1px solid var(--line-2)">
    This advice is computer generated and valid without signature. Queries about this entry should quote the
    reference above and reach us at <?php echo e(setting('support_email')); ?>.
  </p>
</div>

<?php layout_foot($ctx);
