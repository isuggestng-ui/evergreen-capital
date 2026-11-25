<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$tab = get('tab', 'transfers');
if (!in_array($tab, array('deposits', 'withdrawals', 'transfers'), true)) $tab = 'transfers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id       = (int)post('id');
    $raw      = post('decision') !== '' ? post('decision') : post('decision_fallback');
    if ($raw !== 'approve' && $raw !== 'decline') {
        flash('error', 'No decision was received, so nothing was changed. Please click Approve or Decline again.');
        redirect('admin/requests.php?tab=' . urlencode(get('tab', 'transfers')));
    }
    $decision = $raw;
    $note     = post('admin_note');
    $type     = post('type');
    $row = null;

    if ($type === 'deposit')    $row = approve_deposit($id, $decision, $note);
    if ($type === 'withdrawal') $row = approve_withdrawal($id, $decision, $note);
    if ($type === 'transfer')   $row = approve_transfer($id, $decision, $note);

    if (!$row) {
        flash('error', 'That item was already handled.');
    } else {
        $cust = find_user($row['user_id']);
        $word = ucfirst($type);
        $body = '<p>Reference <strong>' . e($row['ref']) . '</strong> for ' . money($row['amount']) . '</p>';
        if ($decision === 'approve') {
            $body .= '<p>It has been approved and processed. Your balance reflects it now.</p>';
        } else {
            $body .= '<p>It was declined' . ($note ? ': ' . e($note) : '.') . '</p>
                      <p>Any funds held for this have been released back to your balance.</p>';
        }
        notify_user($cust, $word . ' ' . ($decision === 'approve' ? 'approved' : 'declined'),
            $word . ' ' . ($decision === 'approve' ? 'approved' : 'declined'), $body,
            'View receipt', base_url('user/receipt.php?ref=' . $row['ref']));

        flash('success', $word . ' ' . $row['ref'] . ' ' . ($decision === 'approve' ? 'approved' : 'declined') . '.');
    }
    redirect('admin/requests.php?tab=' . $tab . '&filter=' . urlencode(get('filter', 'pending')));
}

$filter = get('filter', 'pending');
$where  = $filter === 'all' ? '1=1' : 'x.status = ?';
$args   = $filter === 'all' ? array() : array($filter);

$table = $tab === 'deposits' ? 'deposits' : ($tab === 'withdrawals' ? 'withdrawals' : 'transfers');
$sql = "SELECT x.*, u.first_name, u.last_name, u.email, u.account_number, u.balance
        FROM $table x JOIN users u ON u.id = x.user_id
        WHERE $where ORDER BY x.id DESC LIMIT 100";
$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$p = pending_counts();

layout_head('Approvals', 'admin');
page_title('Approvals', 'Nothing settles until you say so. Declining releases any funds held.');
?>

<div class="tabs">
  <?php
  $tabs = array('transfers' => 'Transfers', 'deposits' => 'Deposits', 'withdrawals' => 'Withdrawals');
  foreach ($tabs as $k => $label) {
      $n = $p[$k];
      echo '<a class="' . ($tab === $k ? 'on' : '') . '" href="' . base_url('admin/requests.php?tab=' . $k) . '">'
         . $label . ($n > 0 ? '<span class="n">' . $n . '</span>' : '') . '</a>';
  } ?>
</div>

<div class="flexbar" style="margin-bottom:16px">
  <span class="small muted">Showing</span>
  <?php foreach (array('pending' => 'Waiting', 'approved' => 'Approved', 'completed' => 'Completed', 'declined' => 'Declined', 'all' => 'Everything') as $k => $label) {
      if ($tab === 'transfers' && $k === 'approved') continue;
      if ($tab !== 'transfers' && $k === 'completed') continue;
      echo '<a class="btn btn-sm' . ($filter === $k ? ' btn-primary' : '') . '" href="'
         . base_url('admin/requests.php?tab=' . $tab . '&filter=' . $k) . '">' . $label . '</a>';
  } ?>
</div>

<?php if (!$rows) { ?>
  <section class="card"><div class="empty">
    <h3>Nothing here</h3>
    <p>No <?php echo e($tab); ?> match this filter. That is usually good news.</p>
  </div></section>
<?php } else { ?>

<div class="stack">
<?php foreach ($rows as $r) {
    $isPending = $r['status'] === 'pending';
    $type = $tab === 'deposits' ? 'deposit' : ($tab === 'withdrawals' ? 'withdrawal' : 'transfer'); ?>
  <section class="card">
    <div class="flexbar" style="justify-content:space-between;align-items:flex-start;margin-bottom:16px">
      <div>
        <span class="eyebrow" style="margin-bottom:4px"><?php echo e($r['ref']); ?> · <?php echo nice_date($r['created_at']); ?></span>
        <h2 style="font:400 27px/1 var(--serif)"><?php echo money($r['amount']); ?>
          <?php if (isset($r['fee']) && (float)$r['fee'] > 0) echo '<small class="muted" style="font:400 14px var(--sans)"> + ' . money($r['fee']) . ' fee</small>'; ?>
        </h2>
        <a href="<?php echo base_url('admin/user.php?id=' . (int)$r['user_id']); ?>" class="small">
          <?php echo e($r['first_name'] . ' ' . $r['last_name']); ?></a>
        <span class="small muted">· <?php echo e($r['account_number']); ?> · balance <?php echo money($r['balance']); ?></span>
      </div>
      <?php echo status_pill($r['status']); ?>
    </div>

    <div class="grid g2" style="gap:14px">
      <dl class="kv">
        <?php if ($tab === 'transfers') { ?>
          <dt>Type</dt><dd><?php echo e(ucfirst($r['kind'])); ?></dd>
          <dt>Beneficiary</dt><dd style="font-family:var(--sans)"><?php echo e($r['beneficiary_name']); ?></dd>
          <dt>Account</dt><dd><?php echo e($r['beneficiary_account']); ?></dd>
          <dt>Bank</dt><dd style="font-family:var(--sans)"><?php echo e($r['bank_name']); ?></dd>
          <?php if ($r['swift']) { ?><dt>SWIFT</dt><dd><?php echo e($r['swift']); ?></dd><?php } ?>
          <?php if ($r['iban']) { ?><dt>IBAN</dt><dd><?php echo e($r['iban']); ?></dd><?php } ?>
          <?php if ($r['country']) { ?><dt>Country</dt><dd><?php echo e($r['country']); ?></dd><?php } ?>
          <?php if ($r['narration']) { ?><dt>Narration</dt><dd style="font-family:var(--sans)"><?php echo e($r['narration']); ?></dd><?php } ?>
        <?php } elseif ($tab === 'deposits') { ?>
          <dt>Method</dt><dd style="font-family:var(--sans)"><?php echo e($r['method']); ?></dd>
          <?php if ($r['note']) { ?><dt>Customer note</dt><dd style="font-family:var(--sans)"><?php echo e($r['note']); ?></dd><?php } ?>
          <?php if ($r['proof']) { ?>
            <dt>Proof</dt><dd><a href="<?php echo base_url($r['proof']); ?>" target="_blank" rel="noopener">Open attachment</a></dd>
          <?php } ?>
        <?php } else { ?>
          <dt>Method</dt><dd style="font-family:var(--sans)"><?php echo e($r['method']); ?></dd>
          <dt>Destination</dt><dd style="font-family:var(--sans)"><?php echo e($r['destination']); ?></dd>
        <?php } ?>
        <?php if ($r['admin_note']) { ?><dt>Your note</dt><dd style="font-family:var(--sans)"><?php echo e($r['admin_note']); ?></dd><?php } ?>
      </dl>

      <?php if ($isPending) { ?>
      <div>
        <form method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
          <input type="hidden" name="type" value="<?php echo e($type); ?>">
          <div class="field">
            <label>Note <span class="muted">(shown to the customer if you decline)</span></label>
            <textarea name="admin_note" rows="2" placeholder="Funds confirmed against our statement"></textarea>
          </div>
          <input type="hidden" name="decision_fallback" value="">
          <div class="flexbar">
            <button class="btn btn-primary" type="submit" name="decision" value="approve" onclick="if(this.form.decision_fallback)this.form.decision_fallback.value='approve';"
              data-confirm="Approve <?php echo e($r['ref']); ?> for <?php echo money($r['amount']); ?>?">Approve</button>
            <button class="btn btn-danger" type="submit" name="decision" value="decline" onclick="if(this.form.decision_fallback)this.form.decision_fallback.value='decline';"
              data-confirm="Decline <?php echo e($r['ref']); ?> and release the held funds?">Decline</button>
          </div>
        </form>
        <?php if ($tab === 'withdrawals' || $tab === 'transfers') { ?>
          <p class="small muted" style="margin-top:12px">
            <?php echo money($r['amount'] + (float)$r['fee']); ?> is held against this customer's balance right now.
            Declining puts it straight back.</p>
        <?php } ?>
      </div>
      <?php } else { ?>
      <div class="flexbar" style="align-items:flex-start">
        <a class="btn btn-sm" href="<?php echo base_url('user/receipt.php?ref=' . urlencode($r['ref'])); ?>">View receipt</a>
        <span class="small muted">Handled <?php echo nice_date($r['approved_at']); ?></span>
      </div>
      <?php } ?>
    </div>
  </section>
<?php } ?>
</div>
<?php } ?>

<?php layout_foot('admin');
