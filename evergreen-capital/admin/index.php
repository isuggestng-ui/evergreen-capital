<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$p = pending_counts();
$totalHeld  = sum_where('users', 'balance', "role = 'user'");
$customers  = count_where('users', "role = 'user'");
$activeCust = count_where('users', "role = 'user' AND status = 'active'");
$loanBook   = sum_where('loans', 'total_repayable - amount_repaid', "status = 'active'");
$today      = date('Y-m-d') . ' 00:00:00';
$inToday    = sum_where('transactions', 'amount', "direction='credit' AND status='completed' AND created_at >= ?", array($today));
$outToday   = sum_where('transactions', 'amount+fee', "direction='debit' AND status='completed' AND created_at >= ?", array($today));
$feesMonth  = sum_where('transactions', 'fee', "status='completed' AND created_at >= ?", array(date('Y-m-01') . ' 00:00:00'));

$recent = db()->query('SELECT t.*, u.first_name, u.last_name, u.account_number
                       FROM transactions t JOIN users u ON u.id = t.user_id
                       ORDER BY t.id DESC LIMIT 10')->fetchAll();

$newUsers = db()->query("SELECT * FROM users WHERE role='user' ORDER BY id DESC LIMIT 6")->fetchAll();

// 14-day flow, drawn as a simple inline bar chart.
$series = array();
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $series[] = array(
        'day' => $d,
        'in'  => sum_where('transactions', 'amount', "direction='credit' AND status='completed' AND created_at >= ? AND created_at <= ?", array($d . ' 00:00:00', $d . ' 23:59:59')),
        'out' => sum_where('transactions', 'amount+fee', "direction='debit' AND status='completed' AND created_at >= ? AND created_at <= ?", array($d . ' 00:00:00', $d . ' 23:59:59')),
    );
}
$peak = 1;
foreach ($series as $s) $peak = max($peak, $s['in'], $s['out']);

$queue = $p['deposits'] + $p['withdrawals'] + $p['transfers'] + $p['loans'] + $p['cards'] + $p['kyc'] + $p['enquiries'];

layout_head('Dashboard', 'admin');
page_title('Good day, ' . $admin['first_name'],
    $queue > 0 ? $queue . ' item' . ($queue === 1 ? '' : 's') . ' need a decision from you.' : 'Nothing is waiting on you. The queue is clear.');
?>

<div class="grid g4" style="margin-bottom:18px">
  <div class="stat"><span>Customer funds held</span><b><?php echo money($totalHeld); ?></b>
    <small><?php echo $customers; ?> customers · <?php echo $activeCust; ?> active</small></div>
  <div class="stat up"><span>In today</span><b><?php echo money($inToday); ?></b><small>Completed credits</small></div>
  <div class="stat down"><span>Out today</span><b><?php echo money($outToday); ?></b><small>Debits and fees</small></div>
  <div class="stat"><span>Loan book</span><b><?php echo money($loanBook); ?></b><small>Outstanding principal and interest</small></div>
</div>

<div class="grid g-main" style="align-items:start;margin-bottom:18px">
  <section class="card">
    <div class="flexbar" style="justify-content:space-between;margin-bottom:18px">
      <div><span class="eyebrow" style="margin-bottom:2px">Last 14 days</span>
        <h2>Money in and out</h2></div>
      <span class="small muted">Fees this month: <strong><?php echo money($feesMonth); ?></strong></span>
    </div>
    <div style="display:flex;align-items:flex-end;gap:6px;height:150px">
      <?php foreach ($series as $s) {
        $hIn  = max(2, round(($s['in'] / $peak) * 130));
        $hOut = max(2, round(($s['out'] / $peak) * 130)); ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"
             title="<?php echo e(date('D j M', strtotime($s['day']))); ?> — in <?php echo money($s['in']); ?>, out <?php echo money($s['out']); ?>">
          <div style="display:flex;align-items:flex-end;gap:2px;height:130px">
            <i style="display:block;width:9px;height:<?php echo $hIn; ?>px;background:var(--ink-3);border-radius:2px 2px 0 0"></i>
            <i style="display:block;width:9px;height:<?php echo $hOut; ?>px;background:var(--brass);border-radius:2px 2px 0 0"></i>
          </div>
          <small style="font-size:9.5px;color:var(--muted)"><?php echo date('j', strtotime($s['day'])); ?></small>
        </div>
      <?php } ?>
    </div>
    <div class="flexbar small muted" style="margin-top:12px;gap:16px">
      <span><i style="display:inline-block;width:9px;height:9px;background:var(--ink-3);border-radius:2px"></i> Money in</span>
      <span><i style="display:inline-block;width:9px;height:9px;background:var(--brass);border-radius:2px"></i> Money out</span>
    </div>
  </section>

  <section class="card">
    <span class="eyebrow">Waiting on you</span>
    <h2 style="margin-bottom:14px">The queue</h2>
    <?php
    $queues = array(
      array('Deposits to confirm', $p['deposits'], 'requests.php?tab=deposits'),
      array('Withdrawals to release', $p['withdrawals'], 'requests.php?tab=withdrawals'),
      array('Transfers to approve', $p['transfers'], 'requests.php?tab=transfers'),
      array('Loan applications', $p['loans'], 'loans.php'),
      array('Card requests', $p['cards'], 'cards.php'),
      array('KYC documents', $p['kyc'], 'kyc.php'),
      array('Website enquiries', $p['enquiries'], 'enquiries.php'),
    );
    foreach ($queues as $q) { ?>
      <a href="<?php echo base_url('admin/' . $q[2]); ?>"
         class="flexbar" style="justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--line-2)">
        <span style="font-size:14px;color:var(--text)"><?php echo e($q[0]); ?></span>
        <?php echo $q[1] > 0
            ? '<span class="pill pill-wait">' . (int)$q[1] . ' waiting</span>'
            : '<span class="small muted">Clear</span>'; ?>
      </a>
    <?php } ?>
  </section>
</div>

<div class="grid g-main" style="align-items:start">
  <section class="card card-flush">
    <div class="card-head">
      <h2>Latest ledger entries</h2>
      <a class="btn btn-sm" href="<?php echo base_url('admin/transactions.php'); ?>">Full ledger</a>
    </div>
    <?php if (!$recent) { ?>
      <div class="empty"><h3>The ledger is empty</h3><p>Entries appear here as soon as customers start moving money.</p></div>
    <?php } else { ?>
    <div class="tablewrap"><table class="tbl">
      <thead><tr><th>Customer</th><th>Description</th><th>Status</th><th class="right">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $t) { ?>
        <tr>
          <td><a href="<?php echo base_url('admin/user.php?id=' . (int)$t['user_id']); ?>">
              <strong><?php echo e($t['first_name'] . ' ' . $t['last_name']); ?></strong></a><br>
            <small class="ref"><?php echo e($t['account_number']); ?></small></td>
          <td class="tdesc"><strong><?php echo e(str_short($t['description'], 40)); ?></strong>
            <small class="ref"><?php echo e($t['ref']); ?> · <?php echo nice_date($t['created_at'], false); ?></small></td>
          <td><?php echo status_pill($t['status']); ?></td>
          <td class="num <?php echo $t['direction'] === 'credit' ? 'amt-credit' : 'amt-debit'; ?>">
            <?php echo ($t['direction'] === 'credit' ? '+' : '−') . money($t['amount']); ?></td>
        </tr>
      <?php } ?>
      </tbody></table></div>
    <?php } ?>
  </section>

  <section class="card card-flush">
    <div class="card-head"><h2>Newest customers</h2>
      <a class="btn btn-sm" href="<?php echo base_url('admin/users.php'); ?>">All customers</a></div>
    <?php if (!$newUsers) { ?>
      <div class="empty"><h3>No customers yet</h3><p>Registrations will show up here.</p></div>
    <?php } else { ?>
    <div class="tablewrap"><table class="tbl"><tbody>
      <?php foreach ($newUsers as $n) { ?>
        <tr>
          <td>
            <a href="<?php echo base_url('admin/user.php?id=' . (int)$n['id']); ?>"><strong><?php echo e(user_name($n)); ?></strong></a>
            <br><small class="muted"><?php echo e($n['email']); ?></small>
          </td>
          <td class="right"><?php echo status_pill($n['status']); ?><br>
            <small class="num muted"><?php echo money($n['balance']); ?></small></td>
        </tr>
      <?php } ?>
    </tbody></table></div>
    <?php } ?>
  </section>
</div>

<?php layout_foot('admin');
