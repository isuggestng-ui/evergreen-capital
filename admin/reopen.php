<?php
/**
 * Recovery tool — returns wrongly declined items to the approvals queue.
 *
 * Written for the 5 August 2026 issue where the Approve button submitted no
 * decision and the server fell through to "decline". Declining never moved any
 * money: it only set a status and released the hold. So putting an item back to
 * "pending" restores it exactly as the customer submitted it, and you can then
 * approve it normally from the Approvals page.
 *
 * Upload to /admin/reopen.php, use it, then delete the file.
 */
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$kinds = array(
    'transfers'   => array('label' => 'Transfers',   'ref' => true),
    'deposits'    => array('label' => 'Deposits',    'ref' => true),
    'withdrawals' => array('label' => 'Withdrawals', 'ref' => true),
    'loans'       => array('label' => 'Loans',       'ref' => true),
);

$since = get('since') !== '' ? get('since') : date('Y-m-d', strtotime('-30 days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $table = post('table');
    $ids   = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array();

    if (!isset($kinds[$table]) && $table !== 'kyc_documents') {
        flash('error', 'Unknown record type.');
        redirect('admin/reopen.php');
    }
    if (!$ids) {
        flash('error', 'Nothing was ticked.');
        redirect('admin/reopen.php?since=' . urlencode($since));
    }

    $done = 0;
    foreach ($ids as $id) {
        $id = (int)$id;

        if ($table === 'kyc_documents') {
            $st = db()->prepare("SELECT * FROM kyc_documents WHERE id = ? AND status = 'declined'");
            $st->execute(array($id));
            $row = $st->fetch();
            if (!$row) continue;
            db()->prepare("UPDATE kyc_documents SET status = 'pending' WHERE id = ?")->execute(array($id));
            db()->prepare("UPDATE users SET kyc_status = 'pending' WHERE id = ?")->execute(array($row['user_id']));
            $done++;
            audit('reopened_kyc', 'kyc', $id, 'returned to review queue');
            continue;
        }

        $st = db()->prepare("SELECT * FROM $table WHERE id = ? AND status = 'declined'");
        $st->execute(array($id));
        $row = $st->fetch();
        if (!$row) continue;

        // Put the request itself back in the queue.
        db()->prepare("UPDATE $table SET status = 'pending', admin_note = NULL,
                       approved_by = NULL" . ($table === 'loans' ? '' : ', approved_at = NULL') . " WHERE id = ?")
            ->execute(array($id));

        // And re-hold the funds by putting the matching ledger entry back to pending.
        if ($table !== 'loans' && !empty($row['ref'])) {
            db()->prepare("UPDATE transactions SET status = 'pending' WHERE ref = ? AND status = 'declined'")
                ->execute(array($row['ref']));
        }

        $done++;
        audit('reopened_' . $table, $table, $id,
              (isset($row['ref']) ? $row['ref'] . ' ' : '') . money($row['amount']));
    }

    flash('success', $done . ' item' . ($done === 1 ? '' : 's') .
        ' returned to the queue. Approve them from the Approvals page as normal.');
    redirect('admin/reopen.php?since=' . urlencode($since));
}

layout_head('Recovery', 'admin');
page_title('Return declined items to the queue',
    'For items that were declined by mistake. Nothing here moves money — it only puts the request back where it was.');
?>

<div class="alert alert-info"><div>
  <strong>Read this first.</strong> Declining never debited or credited anybody, so putting an item
  back to pending is safe and reversible. Two things to keep in mind:
  <ul style="margin:8px 0 0;padding-left:18px">
    <li>The customer already received a “declined” email. Reopening does <strong>not</strong> send a
      correction — use <a href="<?php echo base_url('admin/messages.php'); ?>">Messaging</a> if you want to explain.</li>
    <li>If you already credited someone by hand to make up for a failed payment, do <strong>not</strong>
      reopen that item as well, or it will go out twice.</li>
  </ul>
</div></div>

<section class="card" style="margin-bottom:18px">
  <form method="get" class="flexbar" style="align-items:end;gap:12px">
    <div class="field mb0"><label for="since">Declined on or after</label>
      <input type="date" id="since" name="since" value="<?php echo e($since); ?>"></div>
    <button class="btn btn-primary" type="submit">Show</button>
    <span class="small muted">Narrow the date range to the day the problem started, so you do not
      reopen anything you meant to decline.</span>
  </form>
</section>

<?php
$found = 0;

foreach ($kinds as $table => $meta) {
    $dateCol = $table === 'loans' ? 'created_at' : 'approved_at';
    $sql = "SELECT x.*, u.first_name, u.last_name, u.account_number
            FROM $table x JOIN users u ON u.id = x.user_id
            WHERE x.status = 'declined' AND COALESCE(x.$dateCol, x.created_at) >= ?
            ORDER BY x.id DESC LIMIT 200";
    $st = db()->prepare($sql);
    $st->execute(array($since . ' 00:00:00'));
    $rows = $st->fetchAll();
    if (!$rows) continue;
    $found += count($rows);
    ?>
    <section class="card card-flush" style="margin-bottom:18px">
      <div class="card-head">
        <h2><?php echo e($meta['label']); ?></h2>
        <span class="small muted"><?php echo count($rows); ?> declined</span>
      </div>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="table" value="<?php echo e($table); ?>">
        <div class="tablewrap">
          <table class="tbl">
            <thead><tr>
              <th style="width:34px"></th><th>Customer</th><th>Reference</th>
              <th>Declined</th><th>Note left</th><th class="right">Amount</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r) { ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$r['id']; ?>"></td>
                <td><a href="<?php echo base_url('admin/user.php?id=' . (int)$r['user_id']); ?>">
                    <strong><?php echo e($r['first_name'] . ' ' . $r['last_name']); ?></strong></a><br>
                  <small class="ref"><?php echo e($r['account_number']); ?></small></td>
                <td class="ref"><?php echo e(isset($r['ref']) ? $r['ref'] : '—'); ?></td>
                <td class="small nowrap"><?php echo nice_date(isset($r['approved_at']) && $r['approved_at']
                      ? $r['approved_at'] : $r['created_at']); ?></td>
                <td class="small muted"><?php echo $r['admin_note'] !== '' && $r['admin_note'] !== null
                      ? e(str_short($r['admin_note'], 40))
                      : '<em>none — likely the button fault</em>'; ?></td>
                <td class="num"><?php echo money($r['amount']); ?></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
        <div class="flexbar" style="padding:14px 18px;border-top:1px solid var(--line-2)">
          <label class="check mb0" style="margin:0">
            <input type="checkbox" onclick="this.closest('form').querySelectorAll('input[name=\'ids[]\']').forEach(function(c){c.checked=this.checked}.bind(this))">
            <span>Select all</span></label>
          <div class="spacer"></div>
          <button class="btn btn-primary" type="submit"
            data-confirm="Return the ticked <?php echo e(strtolower($meta['label'])); ?> to the pending queue?">
            Return ticked to queue</button>
        </div>
      </form>
    </section>
    <?php
}

// KYC documents
$st = db()->prepare("SELECT k.*, u.first_name, u.last_name, u.account_number
                     FROM kyc_documents k JOIN users u ON u.id = k.user_id
                     WHERE k.status = 'declined' AND k.created_at >= ? ORDER BY k.id DESC LIMIT 200");
$st->execute(array($since . ' 00:00:00'));
$kyc = $st->fetchAll();
if ($kyc) {
    $found += count($kyc); ?>
  <section class="card card-flush" style="margin-bottom:18px">
    <div class="card-head"><h2>Identity checks</h2>
      <span class="small muted"><?php echo count($kyc); ?> declined</span></div>
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="table" value="kyc_documents">
      <div class="tablewrap"><table class="tbl">
        <thead><tr><th style="width:34px"></th><th>Customer</th><th>Document</th><th>Submitted</th></tr></thead>
        <tbody>
        <?php foreach ($kyc as $r) { ?>
          <tr>
            <td><input type="checkbox" name="ids[]" value="<?php echo (int)$r['id']; ?>"></td>
            <td><a href="<?php echo base_url('admin/user.php?id=' . (int)$r['user_id']); ?>">
                <strong><?php echo e($r['first_name'] . ' ' . $r['last_name']); ?></strong></a><br>
              <small class="ref"><?php echo e($r['account_number']); ?></small></td>
            <td class="small"><?php echo e($r['doc_type']); ?></td>
            <td class="small nowrap"><?php echo nice_date($r['created_at']); ?></td>
          </tr>
        <?php } ?>
        </tbody></table></div>
      <div class="flexbar" style="padding:14px 18px;border-top:1px solid var(--line-2)">
        <div class="spacer"></div>
        <button class="btn btn-primary" type="submit"
          data-confirm="Return the ticked identity checks to the review queue?">Return ticked to queue</button>
      </div>
    </form>
  </section>
<?php }

if (!$found) { ?>
  <section class="card"><div class="empty">
    <h3>Nothing declined in that window</h3>
    <p>Either the date is too recent, or there is nothing to put back. Widen the range above.</p>
  </div></section>
<?php } ?>

<section class="card">
  <span class="eyebrow">When you are finished</span>
  <h2>Delete this file</h2>
  <p class="sub mb0">This is a one-off repair tool, not part of the app. Once your queue is
     straight, remove <code>admin/reopen.php</code> from the server.</p>
</section>

<?php layout_foot('admin');
