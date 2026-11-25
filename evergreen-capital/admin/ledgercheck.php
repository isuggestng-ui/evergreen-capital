<?php
/**
 * Ledger health check — READ ONLY.
 *
 * Recomputes every customer's balance from their completed transactions and
 * compares it with the balance stored on the account. If the two agree, the
 * ledger is sound.
 *
 * This file contains no INSERT, UPDATE or DELETE. It cannot change anything.
 * Upload to /admin/ledgercheck.php, read it, then delete it.
 */
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$users = db()->query("SELECT id, first_name, last_name, email, account_number, balance
                      FROM users ORDER BY id")->fetchAll();

$rows = array();
$bad = 0;
$heldTotal = 0.0;

foreach ($users as $u) {
    $st = db()->prepare("SELECT
            COALESCE(SUM(CASE WHEN direction='credit' AND status='completed' THEN amount ELSE 0 END),0) AS cr,
            COALESCE(SUM(CASE WHEN direction='debit'  AND status='completed' THEN amount + fee ELSE 0 END),0) AS dr,
            COALESCE(SUM(CASE WHEN direction='debit'  AND status='pending'   THEN amount + fee ELSE 0 END),0) AS held,
            COALESCE(SUM(CASE WHEN status='declined' THEN 1 ELSE 0 END),0) AS declined,
            COUNT(*) AS entries
          FROM transactions WHERE user_id = ?");
    $st->execute(array($u['id']));
    $t = $st->fetch();

    $expected = round((float)$t['cr'] - (float)$t['dr'], 2);
    $actual   = round((float)$u['balance'], 2);
    $diff     = round($actual - $expected, 2);
    $heldTotal += (float)$t['held'];
    if (abs($diff) >= 0.01) $bad++;

    $rows[] = array('u' => $u, 't' => $t, 'expected' => $expected, 'actual' => $actual, 'diff' => $diff);
}

$declinedTx = count_where('transactions', "status = 'declined'");

layout_head('Ledger check', 'admin');
page_title('Ledger health check',
    'Recomputes every balance from the transaction history. This page only reads — it changes nothing.');
?>

<?php if ($bad === 0) { ?>
  <div class="alert alert-success"><div>
    <strong>The ledger is sound.</strong> Every one of the <?php echo count($rows); ?> account<?php
    echo count($rows) === 1 ? '' : 's'; ?> matches the sum of its completed transactions to the cent.
  </div></div>
<?php } else { ?>
  <div class="alert alert-error"><div>
    <strong><?php echo $bad; ?> account<?php echo $bad === 1 ? '' : 's'; ?> do not match.</strong>
    The mismatched rows are highlighted below. A difference usually means a balance was changed
    outside the ledger — send me the figures rather than adjusting anything by hand.
  </div></div>
<?php } ?>

<div class="grid g4" style="margin-bottom:18px">
  <div class="stat"><span>Accounts checked</span><b><?php echo count($rows); ?></b></div>
  <div class="stat<?php echo $bad ? ' down' : ' up'; ?>"><span>Mismatches</span><b><?php echo $bad; ?></b></div>
  <div class="stat"><span>Currently held</span><b><?php echo money($heldTotal); ?></b>
    <small>Pending payments, not yet taken</small></div>
  <div class="stat"><span>Declined entries</span><b><?php echo $declinedTx; ?></b>
    <small>None of these moved money</small></div>
</div>

<section class="card card-flush">
  <div class="card-head">
    <h2>Account by account</h2>
    <span class="small muted">Stored balance vs. the sum of completed entries</span>
  </div>
  <div class="tablewrap">
    <table class="tbl">
      <thead><tr>
        <th>Account</th><th class="right">Credits in</th><th class="right">Debits out</th>
        <th class="right">Should be</th><th class="right">Stored</th><th class="right">Difference</th>
        <th class="right">Held</th><th class="right">Entries</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r) {
        $off = abs($r['diff']) >= 0.01; ?>
        <tr<?php echo $off ? ' style="background:var(--debit-bg)"' : ''; ?>>
          <td><a href="<?php echo base_url('admin/user.php?id=' . (int)$r['u']['id']); ?>">
              <strong><?php echo e($r['u']['first_name'] . ' ' . $r['u']['last_name']); ?></strong></a><br>
            <small class="ref"><?php echo e($r['u']['account_number']); ?></small></td>
          <td class="num amt-credit"><?php echo money($r['t']['cr']); ?></td>
          <td class="num amt-debit"><?php echo money($r['t']['dr']); ?></td>
          <td class="num"><?php echo money($r['expected']); ?></td>
          <td class="num"><?php echo money($r['actual']); ?></td>
          <td class="num"><?php echo $off
              ? '<strong style="color:var(--debit)">' . money($r['diff']) . '</strong>'
              : '<span class="muted">0.00</span>'; ?></td>
          <td class="num muted"><?php echo (float)$r['t']['held'] > 0 ? money($r['t']['held']) : '—'; ?></td>
          <td class="num muted"><?php echo (int)$r['t']['entries']; ?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" style="margin-top:18px">
  <span class="eyebrow">Reading this</span>
  <ul class="small" style="padding-left:18px;margin:0;color:var(--muted);line-height:1.85">
    <li><strong>Should be</strong> is calculated fresh from the transaction history: completed
        credits, minus completed debits and their fees.</li>
    <li><strong>Held</strong> is money committed to a payment that has not settled. It is not
        subtracted from the balance, which is why the customer sees a lower <em>available</em>
        balance than their ledger balance.</li>
    <li><strong>Declined entries</strong> never moved money. Declining sets a status and releases
        the hold — nothing is credited or debited.</li>
    <li>Delete <code>admin/ledgercheck.php</code> when you are finished with it.</li>
  </ul>
</section>

<?php layout_foot('admin');
