<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

$from = get('from') ? get('from') : date('Y-m-01', strtotime('-2 months'));
$to   = get('to') ? get('to') : date('Y-m-d');
$show = get('go') === '1';

$rows = array();
$opening = 0.0;
if ($show) {
    $st = db()->prepare("SELECT * FROM transactions
        WHERE user_id = ? AND status = 'completed' AND created_at >= ? AND created_at <= ?
        ORDER BY created_at ASC, id ASC");
    $st->execute(array($u['id'], $from . ' 00:00:00', $to . ' 23:59:59'));
    $rows = $st->fetchAll();

    $inBefore  = sum_where('transactions', 'amount', "user_id=? AND status='completed' AND direction='credit' AND created_at < ?", array($u['id'], $from . ' 00:00:00'));
    $outBefore = sum_where('transactions', 'amount+fee', "user_id=? AND status='completed' AND direction='debit' AND created_at < ?", array($u['id'], $from . ' 00:00:00'));
    $opening = round($inBefore - $outBefore, 2);
}

layout_head('Statement', 'user');
page_title('Account statement', 'Pick a period and print it, or save it as a PDF from the print dialog.',
    $show ? '<button class="btn btn-primary noprint" onclick="window.print()">Print or save as PDF</button>' : '');
?>

<section class="card noprint" style="margin-bottom:18px">
  <form method="get" class="flexbar" style="align-items:end;gap:14px">
    <input type="hidden" name="go" value="1">
    <div class="field mb0" style="min-width:170px"><label for="from">From</label>
      <input type="date" id="from" name="from" value="<?php echo e($from); ?>"></div>
    <div class="field mb0" style="min-width:170px"><label for="to">To</label>
      <input type="date" id="to" name="to" value="<?php echo e($to); ?>"></div>
    <button class="btn btn-primary" type="submit">Generate statement</button>
    <div class="spacer"></div>
    <span class="small muted">Quick picks:
      <a href="?go=1&from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>">This month</a> ·
      <a href="?go=1&from=<?php echo date('Y-m-d', strtotime('-90 days')); ?>&to=<?php echo date('Y-m-d'); ?>">Last 90 days</a> ·
      <a href="?go=1&from=<?php echo date('Y-01-01'); ?>&to=<?php echo date('Y-m-d'); ?>">Year to date</a>
    </span>
  </form>
</section>

<?php if (!$show) { ?>
  <section class="card"><div class="empty">
    <h3>Choose your dates</h3>
    <p>The statement shows every completed entry in the window, with a running balance and totals.</p>
  </div></section>
<?php } else {
  $totalIn = 0; $totalOut = 0;
  foreach ($rows as $r) {
      if ($r['direction'] === 'credit') $totalIn += (float)$r['amount'];
      else $totalOut += (float)$r['amount'] + (float)$r['fee'];
  }
?>
<div class="sheet" style="max-width:900px">
  <div class="sheet-head">
    <div>
      <div class="m"><?php echo e(setting('site_name')); ?></div>
      <small><?php echo e(setting('site_legal', '')); ?><br><?php echo e(setting('address', '')); ?></small>
    </div>
    <div class="right">
      <span class="eyebrow">Statement of account</span>
      <strong style="display:block"><?php echo nice_date($from, false); ?> — <?php echo nice_date($to, false); ?></strong>
      <small style="display:block;color:var(--muted);margin-top:4px">Issued <?php echo nice_date(now(), false); ?></small>
    </div>
  </div>

  <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:22px">
    <div>
      <span class="eyebrow">Account holder</span>
      <strong style="display:block;font-size:16px"><?php echo e(user_name($u)); ?></strong>
      <span class="small muted"><?php echo e($u['address'] ? $u['address'] . ', ' : ''); ?><?php echo e($u['city']); ?> <?php echo e($u['country']); ?></span>
    </div>
    <div>
      <span class="eyebrow">Account</span>
      <span class="mono" style="display:block"><?php echo e($u['account_number']); ?></span>
      <span class="small muted"><?php echo e($u['account_type']); ?> · <?php echo e($u['currency']); ?></span>
    </div>
    <div class="right">
      <span class="eyebrow">Closing balance</span>
      <strong style="font:400 26px/1 var(--serif)"><?php echo money($u['balance']); ?></strong>
    </div>
  </div>

  <div class="grid g4" style="gap:12px;margin-bottom:22px">
    <div class="stat" style="box-shadow:none"><span>Opening</span><b><?php echo money($opening); ?></b></div>
    <div class="stat up" style="box-shadow:none"><span>Paid in</span><b><?php echo money($totalIn); ?></b></div>
    <div class="stat down" style="box-shadow:none"><span>Paid out</span><b><?php echo money($totalOut); ?></b></div>
    <div class="stat" style="box-shadow:none"><span>Entries</span><b><?php echo count($rows); ?></b></div>
  </div>

  <?php if (!$rows) { ?>
    <p class="muted center" style="padding:30px 0">No completed entries in this period.</p>
  <?php } else { ?>
  <div class="tablewrap">
    <table class="tbl" style="font-size:13.4px">
      <thead><tr><th>Date</th><th>Description</th><th>Reference</th><th class="right">Paid out</th><th class="right">Paid in</th><th class="right">Balance</th></tr></thead>
      <tbody>
      <?php $run = $opening; foreach ($rows as $r) {
        $isCredit = $r['direction'] === 'credit';
        $run += $isCredit ? (float)$r['amount'] : -((float)$r['amount'] + (float)$r['fee']); ?>
        <tr>
          <td class="small nowrap"><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
          <td class="small"><?php echo e(str_short($r['description'], 46)); ?></td>
          <td class="ref"><?php echo e($r['ref']); ?></td>
          <td class="num"><?php echo $isCredit ? '' : money((float)$r['amount'] + (float)$r['fee']); ?></td>
          <td class="num"><?php echo $isCredit ? money($r['amount']) : ''; ?></td>
          <td class="num muted"><?php echo money($run); ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
  <?php } ?>

  <p class="small muted" style="margin-top:24px;padding-top:16px;border-top:1px solid var(--line-2)">
    Please check this statement and report anything that looks wrong within 30 days.
    <?php echo e(setting('support_email')); ?> · <?php echo e(setting('support_phone')); ?>
  </p>
</div>
<?php } ?>

<?php layout_foot('user');
