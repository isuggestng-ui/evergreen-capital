<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

/** Undo a completed entry's effect on the balance. */
function unapply_tx($tx)
{
    if ($tx['status'] !== 'completed') return;
    $bal = get_balance($tx['user_id']);
    $bal = ($tx['direction'] === 'credit')
        ? $bal - (float)$tx['amount']
        : $bal + ((float)$tx['amount'] + (float)$tx['fee']);
    set_balance($tx['user_id'], $bal);
}

/** Apply an entry's effect to the balance. Returns the new balance. */
function apply_tx($userId, $direction, $amount, $fee, $status)
{
    $bal = get_balance($userId);
    if ($status === 'completed') {
        $bal = ($direction === 'credit') ? $bal + (float)$amount : $bal - ((float)$amount + (float)$fee);
        set_balance($userId, $bal);
    }
    return $bal;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');
    $st = db()->prepare('SELECT * FROM transactions WHERE id = ?');
    $st->execute(array($id));
    $tx = $st->fetch();

    if (!$tx) {
        flash('error', 'That entry no longer exists.');
        redirect('admin/transactions.php');
    }

    if (post('action') === 'update') {
        $amount = amount_in('amount');
        $fee    = amount_in('fee');
        $dir    = post('direction') === 'credit' ? 'credit' : 'debit';
        $status = post('status');
        $date   = post('date') !== '' ? date('Y-m-d H:i:s', strtotime(post('date'))) : $tx['created_at'];

        unapply_tx($tx);
        $newBal = apply_tx($tx['user_id'], $dir, $amount, $fee, $status);

        db()->prepare('UPDATE transactions SET direction=?, category=?, amount=?, fee=?, balance_after=?,
                       description=?, channel=?, counterparty=?, status=?, created_at=? WHERE id=?')
            ->execute(array($dir, post('category'), $amount, $fee, round($newBal, 2),
                post('description'), post('channel'), post('counterparty'), $status, $date, $id));

        audit('tx_edited', 'transaction', $id, $tx['ref'] . ' → ' . money($amount) . ' ' . $status);
        flash('success', 'Entry ' . $tx['ref'] . ' updated. Balance is now ' . money($newBal) . '.');
        redirect('admin/transactions.php?user=' . (int)$tx['user_id']);
    }

    if (post('action') === 'delete') {
        unapply_tx($tx);
        db()->prepare('DELETE FROM transactions WHERE id = ?')->execute(array($id));
        audit('tx_deleted', 'transaction', $id, $tx['ref'] . ' ' . money($tx['amount']));
        flash('success', 'Entry deleted and the balance corrected.');
        redirect('admin/transactions.php?user=' . (int)$tx['user_id']);
    }

    if (post('action') === 'reverse') {
        if ($tx['status'] !== 'completed') {
            flash('error', 'Only a completed entry can be reversed.');
            redirect('admin/transactions.php?edit=' . $id);
        }
        // The correcting entry moves the balance back on its own — do not touch it here.
        $opposite = $tx['direction'] === 'credit' ? 'debit' : 'credit';
        $back = $tx['direction'] === 'debit'
            ? (float)$tx['amount'] + (float)$tx['fee']   // give the fee back too
            : (float)$tx['amount'];

        $r = post_transaction($tx['user_id'], $opposite, 'adjustment', $back,
            'Reversal of ' . $tx['ref'] . ($tx['description'] ? ' — ' . $tx['description'] : ''),
            array('channel' => 'system', 'ref' => make_ref('REV')));

        db()->prepare("UPDATE transactions SET status = 'reversed' WHERE id = ?")->execute(array($id));

        audit('tx_reversed', 'transaction', $id, $tx['ref'] . ' → ' . $r['ref']);
        flash('success', 'Entry reversed. ' . money($back) . ' corrected, balance now ' . money($r['balance']) . '.');
        redirect('admin/transactions.php?user=' . (int)$tx['user_id']);
    }
}

/* ---------------- single-entry editor ---------------- */
if (get('edit')) {
    $st = db()->prepare('SELECT t.*, u.first_name, u.last_name, u.account_number, u.balance
                         FROM transactions t JOIN users u ON u.id = t.user_id WHERE t.id = ?');
    $st->execute(array((int)get('edit')));
    $tx = $st->fetch();
    if (!$tx) { flash('error', 'No such entry.'); redirect('admin/transactions.php'); }

    layout_head('Edit entry', 'admin');
    page_title('Edit ledger entry', $tx['ref'] . ' · ' . $tx['first_name'] . ' ' . $tx['last_name'],
        '<a class="btn" href="' . base_url('admin/transactions.php?user=' . (int)$tx['user_id']) . '">Back to ledger</a>');
    ?>
    <div class="grid g-main" style="align-items:start">
      <section class="card">
        <p class="sub">Changing an entry re-computes the customer's balance. The old effect is removed and the new one applied.</p>
        <form method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?php echo (int)$tx['id']; ?>">
          <div class="row3">
            <div class="field"><label>Direction</label>
              <select name="direction">
                <option value="credit"<?php echo $tx['direction'] === 'credit' ? ' selected' : ''; ?>>Credit</option>
                <option value="debit"<?php echo $tx['direction'] === 'debit' ? ' selected' : ''; ?>>Debit</option>
              </select></div>
            <div class="field"><label>Amount</label>
              <div class="money-in"><span><?php echo e(cur()); ?></span>
                <input type="text" name="amount" value="<?php echo number_format((float)$tx['amount'], 2, '.', ''); ?>"></div></div>
            <div class="field"><label>Fee</label>
              <div class="money-in"><span><?php echo e(cur()); ?></span>
                <input type="text" name="fee" value="<?php echo number_format((float)$tx['fee'], 2, '.', ''); ?>"></div></div>
          </div>
          <div class="field"><label>Description</label>
            <input type="text" name="description" value="<?php echo e($tx['description']); ?>" maxlength="180"></div>
          <div class="field"><label>Counterparty</label>
            <input type="text" name="counterparty" value="<?php echo e($tx['counterparty']); ?>"></div>
          <div class="row3">
            <div class="field"><label>Category</label>
              <select name="category">
                <?php foreach (array('adjustment','deposit','withdrawal','transfer_in','transfer_out','charge','loan','repayment') as $c)
                  echo '<option value="' . $c . '"' . ($tx['category'] === $c ? ' selected' : '') . '>' . ucfirst(str_replace('_', ' ', $c)) . '</option>'; ?>
              </select></div>
            <div class="field"><label>Channel</label>
              <input type="text" name="channel" value="<?php echo e($tx['channel']); ?>"></div>
            <div class="field"><label>Status</label>
              <select name="status">
                <?php foreach (array('completed','pending','declined','reversed') as $s)
                  echo '<option value="' . $s . '"' . ($tx['status'] === $s ? ' selected' : '') . '>' . ucfirst($s) . '</option>'; ?>
              </select></div>
          </div>
          <div class="field"><label>Date and time</label>
            <input type="datetime-local" name="date" value="<?php echo date('Y-m-d\TH:i', strtotime($tx['created_at'])); ?>"></div>
          <button class="btn btn-primary" type="submit" data-confirm="Save changes and recalculate the balance?">Save entry</button>
        </form>
      </section>

      <div class="stack">
        <section class="card">
          <span class="eyebrow">Customer</span>
          <dl class="kv">
            <dt>Name</dt><dd style="font-family:var(--sans)"><?php echo e($tx['first_name'] . ' ' . $tx['last_name']); ?></dd>
            <dt>Account</dt><dd><?php echo e($tx['account_number']); ?></dd>
            <dt>Current balance</dt><dd><strong><?php echo money($tx['balance']); ?></strong></dd>
            <dt>Reference</dt><dd><?php echo e($tx['ref']); ?></dd>
          </dl>
          <a class="btn btn-sm" href="<?php echo base_url('admin/user.php?id=' . (int)$tx['user_id']); ?>">Open customer</a>
        </section>

        <section class="card">
          <span class="eyebrow">Other actions</span>
          <?php if ($tx['status'] === 'completed') { ?>
          <form method="post" style="margin-bottom:10px">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="reverse">
            <input type="hidden" name="id" value="<?php echo (int)$tx['id']; ?>">
            <button class="btn btn-block" type="submit"
              data-confirm="Reverse this entry and post a correcting entry?">Reverse this entry</button>
          </form>
          <?php } ?>
          <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo (int)$tx['id']; ?>">
            <button class="btn btn-danger btn-block" type="submit"
              data-confirm="Delete this entry outright? The balance will be corrected.">Delete entry</button>
          </form>
          <p class="small muted" style="margin:12px 0 0">Reversing keeps the audit trail intact and is usually the right choice. Deleting removes the record entirely.</p>
        </section>
      </div>
    </div>
    <?php
    layout_foot('admin');
    exit;
}

/* ---------------- ledger list ---------------- */
$userId = (int)get('user', 0);
$q      = get('q');
$stat   = get('status');
$dir    = get('dir');
$from   = get('from');
$to     = get('to');
$page   = max(1, (int)get('p', 1));
$per    = 30;

$where = '1=1';
$args  = array();
if ($userId) { $where .= ' AND t.user_id = ?'; $args[] = $userId; }
if ($q !== '') {
    $where .= " AND (t.description " . db_like() . " ? OR t.ref " . db_like() . " ? OR u.first_name " . db_like() . " ? OR u.last_name " . db_like() . " ? OR u.account_number " . db_like() . " ?)";
    for ($i = 0; $i < 5; $i++) $args[] = '%' . $q . '%';
}
if ($stat !== '') { $where .= ' AND t.status = ?'; $args[] = $stat; }
if ($dir === 'credit' || $dir === 'debit') { $where .= ' AND t.direction = ?'; $args[] = $dir; }
if ($from !== '') { $where .= ' AND t.created_at >= ?'; $args[] = $from . ' 00:00:00'; }
if ($to !== '') { $where .= ' AND t.created_at <= ?'; $args[] = $to . ' 23:59:59'; }

$countSt = db()->prepare("SELECT COUNT(*) c FROM transactions t JOIN users u ON u.id = t.user_id WHERE $where");
$countSt->execute($args);
$cRow = $countSt->fetch();
$total = (int)$cRow['c'];
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$st = db()->prepare("SELECT t.*, u.first_name, u.last_name, u.account_number
                     FROM transactions t JOIN users u ON u.id = t.user_id
                     WHERE $where ORDER BY t.id DESC LIMIT $per OFFSET $offset");
$st->execute($args);
$rows = $st->fetchAll();

$who = $userId ? find_user($userId) : null;

layout_head('Ledger', 'admin');
page_title('Ledger', $who ? 'Every entry for ' . user_name($who) . ' · ' . $total . ' total'
                          : $total . ' entries across the bank.',
    $who ? '<a class="btn" href="' . base_url('admin/transactions.php') . '">Whole bank</a>' : '');
?>

<section class="card" style="margin-bottom:18px">
  <form method="get" class="grid" style="grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;align-items:end">
    <?php if ($userId) echo '<input type="hidden" name="user" value="' . (int)$userId . '">'; ?>
    <div class="field mb0"><label>Search</label>
      <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Reference, name, account"></div>
    <div class="field mb0"><label>Direction</label>
      <select name="dir">
        <option value="">All</option>
        <option value="credit"<?php echo $dir === 'credit' ? ' selected' : ''; ?>>Credits</option>
        <option value="debit"<?php echo $dir === 'debit' ? ' selected' : ''; ?>>Debits</option>
      </select></div>
    <div class="field mb0"><label>Status</label>
      <select name="status">
        <option value="">All</option>
        <?php foreach (array('completed','pending','declined','reversed') as $s)
          echo '<option value="' . $s . '"' . ($stat === $s ? ' selected' : '') . '>' . ucfirst($s) . '</option>'; ?>
      </select></div>
    <div class="field mb0"><label>From</label><input type="date" name="from" value="<?php echo e($from); ?>"></div>
    <div class="field mb0"><label>To</label><input type="date" name="to" value="<?php echo e($to); ?>"></div>
    <div class="flexbar" style="grid-column:1/-1">
      <button class="btn btn-primary" type="submit">Apply</button>
      <a class="btn" href="<?php echo base_url('admin/transactions.php' . ($userId ? '?user=' . $userId : '')); ?>">Clear</a>
    </div>
  </form>
</section>

<section class="card card-flush">
  <?php if (!$rows) { ?>
    <div class="empty"><h3>Nothing matches</h3><p>Widen the dates or clear the search.</p></div>
  <?php } else { ?>
  <div class="tablewrap"><table class="tbl">
    <thead><tr><th>Customer</th><th>Description</th><th>Date</th><th>Status</th><th class="right">Amount</th><th class="right">Balance</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t) { ?>
      <tr>
        <td><a href="<?php echo base_url('admin/user.php?id=' . (int)$t['user_id']); ?>">
            <strong><?php echo e($t['first_name'] . ' ' . $t['last_name']); ?></strong></a><br>
          <small class="ref"><?php echo e($t['account_number']); ?></small></td>
        <td class="tdesc"><strong><?php echo e(str_short($t['description'], 40)); ?></strong>
          <small class="ref"><?php echo e($t['ref']); ?> · <?php echo e(ucfirst($t['channel'])); ?></small></td>
        <td class="small nowrap"><?php echo nice_date($t['created_at'], false); ?></td>
        <td><?php echo status_pill($t['status']); ?></td>
        <td class="num <?php echo $t['direction'] === 'credit' ? 'amt-credit' : 'amt-debit'; ?>">
          <?php echo ($t['direction'] === 'credit' ? '+' : '−') . money($t['amount']); ?></td>
        <td class="num muted"><?php echo money($t['balance_after']); ?></td>
        <td class="right nowrap">
          <a class="btn btn-sm" href="<?php echo base_url('admin/transactions.php?edit=' . (int)$t['id']); ?>">Edit</a>
          <a class="btn btn-sm" href="<?php echo base_url('user/receipt.php?ref=' . urlencode($t['ref'])); ?>">Receipt</a>
        </td>
      </tr>
    <?php } ?>
    </tbody></table></div>
  <?php if ($pages > 1) {
    $b = array('user' => $userId ? $userId : null, 'q' => $q, 'status' => $stat, 'dir' => $dir, 'from' => $from, 'to' => $to); ?>
    <div class="flexbar" style="padding:14px 18px;border-top:1px solid var(--line-2)">
      <span class="small muted">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
      <div class="spacer"></div>
      <?php if ($page > 1) echo '<a class="btn btn-sm" href="?' . e(http_build_query(array_merge($b, array('p' => $page - 1)))) . '">Previous</a> ';
      if ($page < $pages) echo '<a class="btn btn-sm" href="?' . e(http_build_query(array_merge($b, array('p' => $page + 1)))) . '">Next</a>'; ?>
    </div>
  <?php } ?>
  <?php } ?>
</section>

<?php layout_foot('admin');
