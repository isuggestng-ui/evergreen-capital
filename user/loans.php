<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

if (setting('loans_enabled', '1') !== '1') {
    layout_head('Loans', 'user');
    page_title('Loans', 'Lending is paused right now.');
    echo '<section class="card"><div class="empty"><h3>Applications are closed</h3>
          <p>We are not taking new loan applications at the moment. Check back soon.</p></div></section>';
    layout_foot('user');
    exit;
}

$rate    = (float)setting('loan_rate', '4.5');
$minL    = (float)setting('loan_min', '500');
$maxL    = (float)setting('loan_max', '100000');
$tenures = array_filter(array_map('trim', explode(',', setting('loan_tenures', '3,6,12,24,36'))));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'apply') {
    csrf_check();
    $amount = amount_in('amount');
    $months = (int)post('tenure_months', 12);
    $errs = array();

    if ($amount < $minL || $amount > $maxL) $errs[] = 'Borrow between ' . money($minL) . ' and ' . money($maxL) . '.';
    if ($months < 1) $errs[] = 'Choose a repayment term.';
    if (post('purpose') === '') $errs[] = 'Tell us what the loan is for.';
    if (count_where('loans', "user_id = ? AND status IN ('pending','active')", array($u['id'])) >= 3) {
        $errs[] = 'You already have three open applications or loans.';
    }

    if ($errs) {
        flash('error', implode(' ', $errs));
    } else {
        $q = loan_quote($amount, $months, $rate);
        $ref = make_ref('LON');
        db()->prepare('INSERT INTO loans
            (user_id,ref,amount,interest_rate,tenure_months,monthly_payment,total_repayable,amount_repaid,purpose,details,employment,monthly_income,collateral,status,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(array($u['id'], $ref, $amount, $q['rate'], $months, $q['monthly'], $q['total'], 0,
                post('purpose'), post('details'), post('employment'), amount_in('monthly_income'),
                post('collateral'), 'pending', now()));

        notify_user($u, 'Loan application received — ' . money($amount), 'Application received',
            '<p>Reference <strong>' . e($ref) . '</strong></p>
             <p>' . money($amount) . ' over ' . $months . ' months at ' . $q['rate'] . '% a year.
             Repayment would be ' . money($q['monthly']) . ' a month, ' . money($q['total']) . ' in total.</p>
             <p>Our credit team reviews applications within one working day.</p>',
            'Track it', base_url('user/loans.php'));

        foreach (db()->query("SELECT email FROM users WHERE role='admin'")->fetchAll() as $a) {
            send_mail($a['email'], 'Loan application — ' . money($amount), mail_template('Loan application',
                '<p><strong>' . e(user_name($u)) . '</strong> applied for ' . money($amount) . ' over ' . $months . ' months.</p>
                 <p>Purpose: ' . e(post('purpose')) . '<br>Reference ' . e($ref) . '</p>',
                'Review it', base_url('admin/loans.php')));
        }

        audit('loan_applied', 'loan', 0, $ref . ' ' . money($amount));
        flash('success', 'Application submitted. Reference ' . $ref . '.');
        redirect('user/loans.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'repay') {
    csrf_check();
    $r = repay_loan((int)post('loan_id'), $u['id'], amount_in('amount'));
    flash($r['ok'] ? 'success' : 'error', $r['msg']);
    if ($r['ok'] && $r['status'] === 'repaid') {
        notify_user($u, 'Loan fully repaid', 'Loan cleared',
            '<p>Your loan is settled in full. Nothing further is owed.</p>');
    }
    redirect('user/loans.php');
}

$st = db()->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY id DESC');
$st->execute(array($u['id']));
$loans = $st->fetchAll();

$outstanding = sum_where('loans', 'total_repayable - amount_repaid', "user_id = ? AND status = 'active'", array($u['id']));

layout_head('Loans', 'user');
page_title('Loan centre', 'Fixed rate of ' . $rate . '% a year. See the full cost before you commit.');
?>

<div class="grid g3" style="margin-bottom:18px">
  <div class="stat"><span>Outstanding</span><b><?php echo money($outstanding); ?></b><small>Across active loans</small></div>
  <div class="stat"><span>You can borrow</span><b><?php echo money($minL); ?>–<?php echo money($maxL); ?></b><small>Subject to review</small></div>
  <div class="stat"><span>Rate</span><b><?php echo e($rate); ?>%</b><small>Flat, per year</small></div>
</div>

<div class="grid g-main" style="align-items:start">
  <div class="stack">
    <?php if (!$loans) { ?>
      <section class="card"><div class="empty">
        <img src="<?php echo base_url('assets/img/empty-ledger.svg'); ?>" alt="" width="200" height="130" class="empty-art">
        <h3>No loans yet</h3><p>Use the calculator to see what a loan would cost, then apply in the same breath.</p>
      </div></section>
    <?php } else { foreach ($loans as $l) {
        $out = round((float)$l['total_repayable'] - (float)$l['amount_repaid'], 2);
        $pct = (float)$l['total_repayable'] > 0 ? min(100, ((float)$l['amount_repaid'] / (float)$l['total_repayable']) * 100) : 0; ?>
      <section class="card">
        <div class="flexbar" style="justify-content:space-between;margin-bottom:14px">
          <div>
            <span class="eyebrow" style="margin-bottom:4px"><?php echo e($l['ref']); ?></span>
            <h2 style="font:400 26px/1 var(--serif)"><?php echo money($l['amount']); ?></h2>
          </div>
          <?php echo status_pill($l['status']); ?>
        </div>

        <dl class="kv" style="margin-bottom:16px">
          <dt>Purpose</dt><dd style="font-family:var(--sans)"><?php echo e($l['purpose']); ?></dd>
          <dt>Term</dt><dd><?php echo (int)$l['tenure_months']; ?> months at <?php echo e($l['interest_rate']); ?>%</dd>
          <dt>Monthly repayment</dt><dd><?php echo money($l['monthly_payment']); ?></dd>
          <dt>Total repayable</dt><dd><?php echo money($l['total_repayable']); ?></dd>
          <?php if ($l['status'] === 'active' || $l['status'] === 'repaid') { ?>
            <dt>Repaid so far</dt><dd><?php echo money($l['amount_repaid']); ?></dd>
            <dt>Still owing</dt><dd><strong><?php echo money(max(0, $out)); ?></strong></dd>
            <dt>Due by</dt><dd><?php echo nice_date($l['due_at'], false); ?></dd>
          <?php } ?>
          <dt>Applied</dt><dd><?php echo nice_date($l['created_at'], false); ?></dd>
        </dl>

        <?php if ($l['admin_note']) { ?>
          <div class="alert alert-info"><div><strong>From the credit team:</strong> <?php echo e($l['admin_note']); ?></div></div>
        <?php } ?>

        <?php if ($l['status'] === 'active') { ?>
          <div class="progress" style="margin-bottom:14px"><i style="width:<?php echo round($pct); ?>%"></i></div>
          <form method="post" class="flexbar" style="gap:10px">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="repay">
            <input type="hidden" name="loan_id" value="<?php echo (int)$l['id']; ?>">
            <div class="money-in" style="flex:1;min-width:150px">
              <span><?php echo e(cur()); ?></span>
              <input type="text" name="amount" inputmode="decimal" value="<?php echo number_format(min((float)$l['monthly_payment'], max(0, $out)), 2, '.', ''); ?>">
            </div>
            <button class="btn btn-primary" type="submit"
              data-confirm="Take this repayment from your balance now?">Make a repayment</button>
          </form>
          <p class="small muted" style="margin:10px 0 0">Comes straight out of your available balance of <?php echo money(available_balance($u['id'])); ?>.</p>
        <?php } ?>
      </section>
    <?php } } ?>
  </div>

  <section class="card">
    <span class="eyebrow">Apply</span>
    <h2>What would it cost?</h2>
    <p class="sub">The figures update as you type.</p>

    <form method="post" id="loanCalc" data-rate="<?php echo e($rate); ?>" data-symbol="<?php echo e(cur()); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="apply">

      <div class="field">
        <label for="amount">How much?</label>
        <div class="money-in"><span><?php echo e(cur()); ?></span>
          <input type="text" id="amount" name="amount" inputmode="decimal" value="<?php echo number_format($minL * 4, 0, '.', ''); ?>" required></div>
        <span class="hint"><?php echo money($minL); ?> to <?php echo money($maxL); ?></span>
      </div>

      <div class="field">
        <label for="tenure">Over how long?</label>
        <select id="tenure" name="tenure_months">
          <?php foreach ($tenures as $t) echo '<option value="' . (int)$t . '"' . ((int)$t === 12 ? ' selected' : '') . '>' . (int)$t . ' months</option>'; ?>
        </select>
      </div>

      <div class="bal" style="margin:18px 0">
        <div class="bal-in">
          <span class="lbl">Monthly repayment</span>
          <span class="fig" id="qMonthly" style="font-size:34px">—</span>
          <div class="meta">
            <div><span>Total repayable</span><b id="qTotal">—</b></div>
            <div><span>Interest</span><b id="qInterest">—</b></div>
          </div>
        </div>
      </div>

      <div class="field"><label for="purpose">What is it for?</label>
        <input type="text" id="purpose" name="purpose" placeholder="Home improvement, business stock…" required></div>
      <div class="field"><label for="details">Tell us a bit more</label>
        <textarea id="details" name="details" rows="3"></textarea></div>
      <div class="row2">
        <div class="field"><label for="employment">Employment</label>
          <select id="employment" name="employment">
            <option>Employed</option><option>Self-employed</option><option>Business owner</option>
            <option>Retired</option><option>Student</option><option>Unemployed</option>
          </select></div>
        <div class="field"><label for="income">Monthly income</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" id="income" name="monthly_income" inputmode="decimal"></div></div>
      </div>
      <div class="field"><label for="collateral">Collateral or guarantor <span class="muted">(optional)</span></label>
        <input type="text" id="collateral" name="collateral"></div>

      <button class="btn btn-primary btn-block" type="submit" data-busy-text="Sending…">Apply for this loan</button>
      <p class="small muted" style="margin:12px 0 0">Applying does not affect your balance. Funds land in your account only once the loan is approved.</p>
    </form>
  </section>
</div>

<?php layout_foot('user');
