<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');

    if (post('action') === 'decide') {
        $raw = post('decision') !== '' ? post('decision') : post('decision_fallback');
        if ($raw !== 'approve' && $raw !== 'decline') {
            flash('error', 'No decision was received, so nothing was changed. Please click Approve or Decline again.');
            redirect('admin/loans.php?filter=' . urlencode(get('filter', 'pending')));
        }
        $decision = $raw;

        // Let the officer change the terms before approving.
        if ($decision === 'approve' && post('amount') !== '') {
            $amount = amount_in('amount');
            $months = (int)post('tenure_months');
            $rate   = (float)post('interest_rate');
            $q = loan_quote($amount, $months, $rate);
            db()->prepare('UPDATE loans SET amount=?, tenure_months=?, interest_rate=?, monthly_payment=?, total_repayable=? WHERE id=?')
                ->execute(array($amount, $months, $rate, $q['monthly'], $q['total'], $id));
        }

        $l = approve_loan($id, $decision, post('admin_note'));
        if (!$l) {
            flash('error', 'That application was already decided.');
        } else {
            $st = db()->prepare('SELECT * FROM loans WHERE id = ?');
            $st->execute(array($id));
            $fresh = $st->fetch();
            $cust = find_user($l['user_id']);

            if ($decision === 'approve') {
                notify_user($cust, 'Loan approved — ' . money($fresh['amount']), 'Your loan is approved',
                    '<p>Good news. We approved <strong>' . money($fresh['amount']) . '</strong> and paid it into your account.</p>
                     <p>Term: ' . (int)$fresh['tenure_months'] . ' months at ' . e($fresh['interest_rate']) . '% a year<br>
                     Monthly repayment: ' . money($fresh['monthly_payment']) . '<br>
                     Total repayable: ' . money($fresh['total_repayable']) . '<br>
                     Final due date: ' . nice_date($fresh['due_at'], false) . '</p>' .
                     (post('admin_note') ? '<p>' . e(post('admin_note')) . '</p>' : ''),
                    'Open loan centre', base_url('user/loans.php'));
            } else {
                notify_user($cust, 'Loan application declined', 'Application declined',
                    '<p>We could not approve your application for ' . money($l['amount']) . ' this time.</p>' .
                    (post('admin_note') ? '<p>' . e(post('admin_note')) . '</p>' : '') .
                    '<p>You are welcome to apply again once your circumstances change.</p>');
            }
            flash('success', 'Loan ' . $l['ref'] . ' ' . ($decision === 'approve' ? 'approved and disbursed' : 'declined') . '.');
        }
        redirect('admin/loans.php?filter=' . urlencode(get('filter', 'pending')));
    }

    if (post('action') === 'repayment') {
        $st = db()->prepare('SELECT * FROM loans WHERE id = ?');
        $st->execute(array($id));
        $l = $st->fetch();
        if ($l) {
            $r = repay_loan($id, $l['user_id'], amount_in('amount'));
            flash($r['ok'] ? 'success' : 'error', $r['msg']);
            audit('loan_repayment_posted', 'loan', $id, money(amount_in('amount')));
        }
        redirect('admin/loans.php?filter=' . urlencode(get('filter', 'active')));
    }

    if (post('action') === 'mark') {
        db()->prepare('UPDATE loans SET status = ?, admin_note = ? WHERE id = ?')
            ->execute(array(post('status'), post('admin_note'), $id));
        audit('loan_marked', 'loan', $id, post('status'));
        flash('success', 'Loan marked as ' . post('status') . '.');
        redirect('admin/loans.php?filter=all');
    }
}

$filter = get('filter', 'pending');
$where  = $filter === 'all' ? '1=1' : 'l.status = ?';
$args   = $filter === 'all' ? array() : array($filter);

$st = db()->prepare("SELECT l.*, u.first_name, u.last_name, u.email, u.account_number, u.balance
                     FROM loans l JOIN users u ON u.id = l.user_id
                     WHERE $where ORDER BY l.id DESC LIMIT 100");
$st->execute($args);
$rows = $st->fetchAll();

$book   = sum_where('loans', 'total_repayable - amount_repaid', "status = 'active'");
$issued = sum_where('loans', 'amount', "status IN ('active','repaid')");
$earned = sum_where('loans', 'total_repayable - amount', "status IN ('active','repaid')");

layout_head('Loans', 'admin');
page_title('Loan book', 'Review applications, set the final terms, and disburse in one step.');
?>

<div class="grid g3" style="margin-bottom:18px">
  <div class="stat"><span>Outstanding</span><b><?php echo money($book); ?></b><small>Principal plus interest still owed</small></div>
  <div class="stat"><span>Lifetime disbursed</span><b><?php echo money($issued); ?></b></div>
  <div class="stat up"><span>Interest booked</span><b><?php echo money($earned); ?></b></div>
</div>

<div class="flexbar" style="margin-bottom:16px">
  <?php foreach (array('pending' => 'Applications', 'active' => 'Active', 'repaid' => 'Repaid', 'declined' => 'Declined', 'defaulted' => 'Defaulted', 'all' => 'Everything') as $k => $label) {
    $n = $k === 'all' ? '' : count_where('loans', 'status = ?', array($k));
    echo '<a class="btn btn-sm' . ($filter === $k ? ' btn-primary' : '') . '" href="'
       . base_url('admin/loans.php?filter=' . $k) . '">' . $label . ($n !== '' && $n > 0 ? ' (' . $n . ')' : '') . '</a>';
  } ?>
</div>

<?php if (!$rows) { ?>
  <section class="card"><div class="empty"><h3>Nothing in this list</h3>
    <p>No loans with that status right now.</p></div></section>
<?php } else { ?>

<div class="stack">
<?php foreach ($rows as $l) {
  $out = round((float)$l['total_repayable'] - (float)$l['amount_repaid'], 2);
  $pct = (float)$l['total_repayable'] > 0 ? min(100, ((float)$l['amount_repaid'] / (float)$l['total_repayable']) * 100) : 0; ?>
  <section class="card">
    <div class="flexbar" style="justify-content:space-between;align-items:flex-start;margin-bottom:16px">
      <div>
        <span class="eyebrow" style="margin-bottom:4px"><?php echo e($l['ref']); ?> · applied <?php echo nice_date($l['created_at'], false); ?></span>
        <h2 style="font:400 27px/1 var(--serif)"><?php echo money($l['amount']); ?>
          <small class="muted" style="font:400 14px var(--sans)">over <?php echo (int)$l['tenure_months']; ?> months</small></h2>
        <a class="small" href="<?php echo base_url('admin/user.php?id=' . (int)$l['user_id']); ?>">
          <?php echo e($l['first_name'] . ' ' . $l['last_name']); ?></a>
        <span class="small muted">· <?php echo e($l['account_number']); ?> · balance <?php echo money($l['balance']); ?></span>
      </div>
      <?php echo status_pill($l['status']); ?>
    </div>

    <div class="grid g2" style="gap:16px">
      <dl class="kv">
        <dt>Purpose</dt><dd style="font-family:var(--sans)"><?php echo e($l['purpose']); ?></dd>
        <?php if ($l['details']) { ?><dt>Detail</dt><dd style="font-family:var(--sans)"><?php echo e($l['details']); ?></dd><?php } ?>
        <dt>Employment</dt><dd style="font-family:var(--sans)"><?php echo e($l['employment']); ?></dd>
        <dt>Monthly income</dt><dd><?php echo money($l['monthly_income']); ?></dd>
        <?php if ($l['collateral']) { ?><dt>Collateral</dt><dd style="font-family:var(--sans)"><?php echo e($l['collateral']); ?></dd><?php } ?>
        <dt>Rate</dt><dd><?php echo e($l['interest_rate']); ?>% a year</dd>
        <dt>Monthly repayment</dt><dd><?php echo money($l['monthly_payment']); ?></dd>
        <dt>Total repayable</dt><dd><?php echo money($l['total_repayable']); ?></dd>
        <?php if ($l['status'] === 'active' || $l['status'] === 'repaid') { ?>
          <dt>Repaid</dt><dd><?php echo money($l['amount_repaid']); ?></dd>
          <dt>Outstanding</dt><dd><strong><?php echo money(max(0, $out)); ?></strong></dd>
          <dt>Due by</dt><dd><?php echo nice_date($l['due_at'], false); ?></dd>
        <?php } ?>
        <?php if ($l['admin_note']) { ?><dt>Note</dt><dd style="font-family:var(--sans)"><?php echo e($l['admin_note']); ?></dd><?php } ?>
      </dl>

      <div>
        <?php if ($l['status'] === 'pending') { ?>
          <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="decide">
            <input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">
            <p class="small muted" style="margin-bottom:12px">Adjust the terms if you are approving something different from what was asked for.</p>
            <div class="row3">
              <div class="field"><label>Amount</label>
                <div class="money-in"><span><?php echo e(cur()); ?></span>
                  <input type="text" name="amount" value="<?php echo number_format((float)$l['amount'], 2, '.', ''); ?>"></div></div>
              <div class="field"><label>Months</label>
                <input type="number" name="tenure_months" value="<?php echo (int)$l['tenure_months']; ?>" min="1" max="360"></div>
              <div class="field"><label>Rate %</label>
                <input type="text" name="interest_rate" value="<?php echo e($l['interest_rate']); ?>"></div>
            </div>
            <div class="field"><label>Note to the customer</label>
              <textarea name="admin_note" rows="2" placeholder="Approved at a shorter term based on income."></textarea></div>
            <input type="hidden" name="decision_fallback" value="">
            <div class="flexbar">
              <button class="btn btn-primary" type="submit" name="decision" value="approve" onclick="if(this.form.decision_fallback)this.form.decision_fallback.value='approve';"
                data-confirm="Approve and pay this loan into the customer's account now?">Approve and disburse</button>
              <button class="btn btn-danger" type="submit" name="decision" value="decline" onclick="if(this.form.decision_fallback)this.form.decision_fallback.value='decline';"
                data-confirm="Decline this application?">Decline</button>
            </div>
          </form>
        <?php } elseif ($l['status'] === 'active') { ?>
          <div class="progress" style="margin-bottom:14px"><i style="width:<?php echo round($pct); ?>%"></i></div>
          <form method="post" class="flexbar" style="gap:10px;margin-bottom:14px">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="repayment">
            <input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">
            <div class="money-in" style="flex:1;min-width:140px"><span><?php echo e(cur()); ?></span>
              <input type="text" name="amount" value="<?php echo number_format(min((float)$l['monthly_payment'], max(0, $out)), 2, '.', ''); ?>"></div>
            <button class="btn btn-primary" type="submit"
              data-confirm="Take this repayment from the customer's balance?">Post repayment</button>
          </form>
          <form method="post" class="flexbar" style="gap:10px">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="mark">
            <input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">
            <select name="status" style="max-width:180px">
              <option value="active">Active</option>
              <option value="repaid">Settled</option>
              <option value="defaulted">Defaulted</option>
            </select>
            <input type="text" name="admin_note" placeholder="Reason" style="flex:1;min-width:140px">
            <button class="btn btn-sm" type="submit">Update status</button>
          </form>
        <?php } else { ?>
          <p class="small muted">This loan is <?php echo e($l['status']); ?>. Nothing further to do.</p>
        <?php } ?>
      </div>
    </div>
  </section>
<?php } ?>
</div>
<?php } ?>

<?php layout_foot('admin');
