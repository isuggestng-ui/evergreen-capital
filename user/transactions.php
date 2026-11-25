<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

$page  = max(1, (int)get('p', 1));
$per   = 25;
$dir   = get('dir');
$stat  = get('status');
$q     = get('q');
$from  = get('from');
$to    = get('to');

$where = 'user_id = ?';
$args  = array($u['id']);
if ($dir === 'credit' || $dir === 'debit') { $where .= ' AND direction = ?'; $args[] = $dir; }
if ($stat !== '') { $where .= ' AND status = ?'; $args[] = $stat; }
if ($q !== '') { $where .= " AND (description " . db_like() . " ? OR ref " . db_like() . " ? OR counterparty " . db_like() . " ?)";
    $args[] = '%' . $q . '%'; $args[] = '%' . $q . '%'; $args[] = '%' . $q . '%'; }
if ($from !== '') { $where .= ' AND created_at >= ?'; $args[] = $from . ' 00:00:00'; }
if ($to !== '') { $where .= ' AND created_at <= ?'; $args[] = $to . ' 23:59:59'; }

$total = count_where('transactions', $where, $args);
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$sql = "SELECT * FROM transactions WHERE $where ORDER BY created_at DESC, id DESC LIMIT $per OFFSET $offset";
$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$sumIn  = sum_where('transactions', 'amount', $where . " AND direction='credit' AND status='completed'", $args);
$sumOut = sum_where('transactions', 'amount+fee', $where . " AND direction='debit' AND status='completed'", $args);

$qs = function ($extra = array()) {
    $base = array('dir' => get('dir'), 'status' => get('status'), 'q' => get('q'), 'from' => get('from'), 'to' => get('to'));
    return '?' . http_build_query(array_merge($base, $extra));
};

layout_head('Transactions', 'user');
page_title('Transactions', $total . ' entr' . ($total === 1 ? 'y' : 'ies') . ' matching your filters.',
    '<a class="btn" href="' . base_url('user/statement.php') . '">Build a statement</a>');
?>

<div class="grid g3" style="margin-bottom:18px">
  <div class="stat up"><span>Credits shown</span><b><?php echo money($sumIn); ?></b></div>
  <div class="stat down"><span>Debits shown</span><b><?php echo money($sumOut); ?></b></div>
  <div class="stat"><span>Net</span><b><?php echo money($sumIn - $sumOut); ?></b></div>
</div>

<section class="card" style="margin-bottom:18px">
  <form method="get" class="grid" style="grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;align-items:end">
    <div class="field mb0"><label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?php echo e($q); ?>" placeholder="Reference, name, note"></div>
    <div class="field mb0"><label for="dir">Direction</label>
      <select id="dir" name="dir">
        <option value="">All</option>
        <option value="credit"<?php echo $dir === 'credit' ? ' selected' : ''; ?>>Money in</option>
        <option value="debit"<?php echo $dir === 'debit' ? ' selected' : ''; ?>>Money out</option>
      </select></div>
    <div class="field mb0"><label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <?php foreach (array('completed', 'pending', 'declined', 'reversed') as $s) {
          echo '<option value="' . $s . '"' . ($stat === $s ? ' selected' : '') . '>' . ucfirst($s) . '</option>';
        } ?>
      </select></div>
    <div class="field mb0"><label for="from">From</label><input type="date" id="from" name="from" value="<?php echo e($from); ?>"></div>
    <div class="field mb0"><label for="to">To</label><input type="date" id="to" name="to" value="<?php echo e($to); ?>"></div>
    <div class="flexbar" style="grid-column:1/-1">
      <button class="btn btn-primary" type="submit">Apply filters</button>
      <a class="btn" href="<?php echo base_url('user/transactions.php'); ?>">Clear</a>
    </div>
  </form>
</section>

<section class="card card-flush">
  <?php if (!$rows) { ?>
    <div class="empty">
      <img src="<?php echo base_url('assets/img/empty-ledger.svg'); ?>" alt="" width="200" height="130" class="empty-art">
      <h3>Nothing matches</h3>
      <p>Try widening the date range or clearing the search.</p>
      <a class="btn" href="<?php echo base_url('user/transactions.php'); ?>">Clear filters</a>
    </div>
  <?php } else { ?>
  <div class="tablewrap">
    <table class="tbl">
      <thead><tr><th>Description</th><th>Channel</th><th>Date</th><th>Status</th><th class="right">Amount</th><th class="right">Balance</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $t) { ?>
        <tr>
          <td class="tdesc">
            <strong><?php echo e(str_short($t['description'], 52)); ?></strong>
            <small class="ref"><?php echo e($t['ref']); ?><?php echo $t['counterparty'] ? ' · ' . e(str_short($t['counterparty'], 28)) : ''; ?></small>
          </td>
          <td class="small muted"><?php echo e(ucfirst($t['channel'])); ?></td>
          <td class="small nowrap"><?php echo nice_date($t['created_at'], false); ?></td>
          <td><?php echo status_pill($t['status']); ?></td>
          <td class="num <?php echo $t['direction'] === 'credit' ? 'amt-credit' : 'amt-debit'; ?>">
            <?php echo ($t['direction'] === 'credit' ? '+' : '−') . money($t['amount']); ?>
            <?php if ((float)$t['fee'] > 0) echo '<br><small class="muted">fee ' . money($t['fee']) . '</small>'; ?>
          </td>
          <td class="num muted"><?php echo money($t['balance_after']); ?></td>
          <td class="right"><a class="btn btn-sm" href="<?php echo base_url('user/receipt.php?ref=' . urlencode($t['ref'])); ?>">Receipt</a></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1) { ?>
    <div class="flexbar" style="padding:14px 18px;border-top:1px solid var(--line-2)">
      <span class="small muted">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
      <div class="spacer"></div>
      <?php if ($page > 1) echo '<a class="btn btn-sm" href="' . e($qs(array('p' => $page - 1))) . '">Previous</a>'; ?>
      <?php if ($page < $pages) echo '<a class="btn btn-sm" href="' . e($qs(array('p' => $page + 1))) . '">Next</a>'; ?>
    </div>
  <?php } ?>
  <?php } ?>
</section>

<?php layout_foot('user');
