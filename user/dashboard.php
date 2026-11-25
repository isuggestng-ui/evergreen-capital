<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();
if ($u['role'] === 'admin') redirect('admin/index.php');

$s = user_stats($u['id']);

$st = db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8');
$st->execute(array($u['id']));
$recent = $st->fetchAll();

$st = db()->prepare("SELECT * FROM loans WHERE user_id = ? AND status IN ('active','pending') ORDER BY id DESC LIMIT 2");
$st->execute(array($u['id']));
$loans = $st->fetchAll();

$st = db()->prepare("SELECT * FROM cards WHERE user_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1");
$st->execute(array($u['id']));
$card = $st->fetch();

$pendingCount = count_where('transactions', "user_id = ? AND status = 'pending'", array($u['id']));

layout_head('Overview', 'user');
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
page_title($greet . ', ' . $u['first_name'],
    'Here is where your money stands as of ' . date('g:ia \o\n F j') . '.');
?>

<?php if ((int)$u['transfer_locked'] === 1) { ?>
  <div class="alert alert-error"><div><strong>Transfers are on hold.</strong>
    <?php echo e($u['lock_reason'] ? $u['lock_reason'] : 'Contact the bank to lift this restriction.'); ?></div></div>
<?php } ?>
<?php if ($u['kyc_status'] === 'unverified') { ?>
  <div class="alert alert-info"><div>Your identity check is outstanding. <a href="<?php echo base_url('user/profile.php#kyc'); ?>">Upload your ID</a> to lift limits on transfers and loans.</div></div>
<?php } ?>

<div class="grid g-main" style="margin-bottom:18px">
  <div class="stack">

    <?php $held = held_amount($u['id']); ?>
    <section class="bal">
      <div class="bal-in">
        <span class="lbl">Available balance</span>
        <span class="fig"><?php echo money((float)$u['balance'] - $held); ?></span>
        <div class="meta">
          <div><span>Account number</span><b><?php echo e($u['account_number']); ?></b></div>
          <div><span>Type</span><b><?php echo e($u['account_type']); ?></b></div>
          <?php if ($held > 0) { ?>
            <div><span>Ledger balance</span><b><?php echo money($u['balance']); ?></b></div>
            <div><span>Held</span><b><?php echo money($held); ?></b></div>
          <?php } else { ?>
            <div><span>Currency</span><b><?php echo e($u['currency']); ?></b></div>
            <div><span>Routing</span><b><?php echo e(setting('routing_number', '—')); ?></b></div>
          <?php } ?>
        </div>
        <div class="bal-actions">
          <a href="<?php echo base_url('user/transfer.php'); ?>">Send money</a>
          <a href="<?php echo base_url('user/deposit.php'); ?>">Add funds</a>
          <a href="<?php echo base_url('user/statement.php'); ?>">Statement</a>
        </div>
      </div>
    </section>

    <div class="grid g3">
      <div class="stat up"><span>Money in</span><b><?php echo money($s['in']); ?></b><small>All completed credits</small></div>
      <div class="stat down"><span>Money out</span><b><?php echo money($s['out']); ?></b><small>Debits and fees</small></div>
      <div class="stat"><span>In progress</span><b><?php echo money($s['pending']); ?></b>
        <small><?php echo $pendingCount; ?> item<?php echo $pendingCount === 1 ? '' : 's'; ?> awaiting approval</small></div>
    </div>

    <section class="card card-flush">
      <div class="card-head">
        <h2>Recent activity</h2>
        <a class="btn btn-sm" href="<?php echo base_url('user/transactions.php'); ?>">See all</a>
      </div>
      <?php if (!$recent) { ?>
        <div class="empty">
          <img src="<?php echo base_url('assets/img/empty-ledger.svg'); ?>" alt="" width="200" height="130" class="empty-art">
          <h3>Nothing has moved yet</h3>
          <p>Once money comes in or goes out, every entry lands here with a receipt.</p>
          <a class="btn btn-primary" href="<?php echo base_url('user/deposit.php'); ?>">Add your first funds</a>
        </div>
      <?php } else { ?>
      <div class="tablewrap">
        <table class="tbl">
          <thead><tr><th>Description</th><th>Date</th><th>Status</th><th class="right">Amount</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($recent as $t) { ?>
            <tr>
              <td class="tdesc">
                <strong><?php echo e(str_short($t['description'], 44)); ?></strong>
                <small class="ref"><?php echo e($t['ref']); ?></small>
              </td>
              <td class="small nowrap"><?php echo nice_date($t['created_at'], false); ?></td>
              <td><?php echo status_pill($t['status']); ?></td>
              <td class="num <?php echo $t['direction'] === 'credit' ? 'amt-credit' : 'amt-debit'; ?>">
                <?php echo ($t['direction'] === 'credit' ? '+' : '−') . money($t['amount']); ?>
              </td>
              <td class="right"><a class="btn btn-sm" href="<?php echo base_url('user/receipt.php?ref=' . urlencode($t['ref'])); ?>">Receipt</a></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <?php } ?>
    </section>
  </div>

  <div class="stack">
    <?php if ($card) { ?>
    <section class="card">
      <span class="eyebrow">Your card</span>
      <div class="cardface<?php echo $card['status'] !== 'approved' ? ' frozen' : ''; ?>" style="max-width:none">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <span class="cbrand"><?php echo e(setting('site_name')); ?></span>
          <span class="muted" style="color:#88A497;font-size:12px;letter-spacing:.1em;text-transform:uppercase"><?php echo e($card['card_type']); ?></span>
        </div>
        <div class="chip"></div>
        <div class="cnum"><?php echo e(mask_card($card['number'])); ?></div>
        <div class="cfoot">
          <div><span>Card holder</span><b><?php echo e(strtoupper(user_name($u))); ?></b></div>
          <div><span>Expires</span><b><?php echo e($card['expiry']); ?></b></div>
          <div><span><?php echo e($card['brand']); ?></span><b>&nbsp;</b></div>
        </div>
      </div>
      <a class="btn btn-sm" style="margin-top:14px" href="<?php echo base_url('user/cards.php'); ?>">Manage cards</a>
    </section>
    <?php } else { ?>
    <section class="card">
      <span class="eyebrow">Cards</span>
      <h2>No card yet</h2>
      <p class="sub">Request a virtual card and start spending as soon as the bank approves it.</p>
      <a class="btn btn-primary btn-sm" href="<?php echo base_url('user/cards.php'); ?>">Request a card</a>
    </section>
    <?php } ?>

    <section class="card">
      <span class="eyebrow">Loans</span>
      <?php if (!$loans) { ?>
        <h2>Borrow from <?php echo money(setting('loan_min', '500')); ?></h2>
        <p class="sub">Fixed rate of <?php echo e(setting('loan_rate', '4.5')); ?>% a year. See your repayment before you apply.</p>
        <a class="btn btn-primary btn-sm" href="<?php echo base_url('user/loans.php'); ?>">Check my rate</a>
      <?php } else { foreach ($loans as $l) {
          $paid = (float)$l['total_repayable'] > 0 ? min(100, ((float)$l['amount_repaid'] / (float)$l['total_repayable']) * 100) : 0; ?>
        <div style="margin-bottom:16px">
          <div class="flexbar" style="justify-content:space-between;margin-bottom:8px">
            <strong><?php echo money($l['amount']); ?></strong>
            <?php echo status_pill($l['status']); ?>
          </div>
          <?php if ($l['status'] === 'active') { ?>
            <div class="progress"><i style="width:<?php echo round($paid); ?>%"></i></div>
            <p class="small muted" style="margin:8px 0 0">
              <?php echo money($l['amount_repaid']); ?> repaid of <?php echo money($l['total_repayable']); ?> ·
              <?php echo money($l['monthly_payment']); ?>/month</p>
          <?php } else { ?>
            <p class="small muted mb0">Submitted <?php echo nice_date($l['created_at'], false); ?>. Waiting on the credit team.</p>
          <?php } ?>
        </div>
      <?php } ?>
        <a class="btn btn-sm" href="<?php echo base_url('user/loans.php'); ?>">Loan centre</a>
      <?php } ?>
    </section>

    <section class="card">
      <span class="eyebrow">Move money</span>
      <div class="stack" style="gap:8px">
        <a class="btn btn-block" href="<?php echo base_url('user/transfer.php'); ?>">Send to another account</a>
        <a class="btn btn-block" href="<?php echo base_url('user/withdraw.php'); ?>">Withdraw to my bank</a>
        <a class="btn btn-block" href="<?php echo base_url('user/beneficiaries.php'); ?>">Saved beneficiaries</a>
      </div>
    </section>
  </div>
</div>

<?php layout_foot('user');
