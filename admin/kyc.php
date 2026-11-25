<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');
    $raw = post('decision') !== '' ? post('decision') : post('decision_fallback');
    if ($raw !== 'approve' && $raw !== 'decline') {
        flash('error', 'No decision was received, so nothing was changed. Please click Approve or Decline again.');
        redirect('admin/kyc.php?filter=' . urlencode(get('filter', 'pending')));
    }
    $decision = $raw === 'approve' ? 'approved' : 'declined';
    $note = post('note');

    $st = db()->prepare('SELECT * FROM kyc_documents WHERE id = ?');
    $st->execute(array($id));
    $d = $st->fetch();

    if ($d) {
        db()->prepare('UPDATE kyc_documents SET status = ?, note = ? WHERE id = ?')
            ->execute(array($decision, $note, $id));
        db()->prepare('UPDATE users SET kyc_status = ? WHERE id = ?')
            ->execute(array($decision === 'approved' ? 'verified' : 'declined', $d['user_id']));
        audit('kyc_' . $decision, 'kyc', $id);

        $cust = find_user($d['user_id']);
        if ($decision === 'approved') {
            notify_user($cust, 'Identity verified', 'You are verified',
                '<p>Your documents checked out. Full transfer limits and loan eligibility now apply to your account.</p>',
                'Open my account', base_url('user/dashboard.php'));
        } else {
            notify_user($cust, 'Identity check failed', 'We could not verify those documents',
                '<p>' . ($note ? e($note) : 'The documents were unclear or did not match your account details.') . '</p>
                 <p>Upload a fresh set from your profile and we will look again.</p>',
                'Try again', base_url('user/profile.php#kyc'));
        }
        flash('success', 'Verification ' . $decision . '.');
    }
    redirect('admin/kyc.php?filter=' . urlencode(get('filter', 'pending')));
}

$filter = get('filter', 'pending');
$where  = $filter === 'all' ? '1=1' : 'k.status = ?';
$args   = $filter === 'all' ? array() : array($filter);

$st = db()->prepare("SELECT k.*, u.first_name, u.last_name, u.email, u.account_number, u.dob, u.address, u.city, u.country
                     FROM kyc_documents k JOIN users u ON u.id = k.user_id
                     WHERE $where ORDER BY k.id DESC LIMIT 60");
$st->execute($args);
$rows = $st->fetchAll();

layout_head('KYC review', 'admin');
page_title('Identity verification', 'Check the documents against the account details before approving.');
?>

<div class="flexbar" style="margin-bottom:16px">
  <?php foreach (array('pending' => 'Waiting', 'approved' => 'Verified', 'declined' => 'Declined', 'all' => 'Everything') as $k => $label) {
    $n = $k === 'all' ? '' : count_where('kyc_documents', 'status = ?', array($k));
    echo '<a class="btn btn-sm' . ($filter === $k ? ' btn-primary' : '') . '" href="'
       . base_url('admin/kyc.php?filter=' . $k) . '">' . $label . ($n !== '' && $n > 0 ? ' (' . $n . ')' : '') . '</a>';
  } ?>
</div>

<?php if (!$rows) { ?>
  <section class="card"><div class="empty"><h3>Nothing to review</h3>
    <p>Documents uploaded by customers land here.</p></div></section>
<?php } else { ?>

<div class="stack">
<?php foreach ($rows as $d) { ?>
  <section class="card">
    <div class="flexbar" style="justify-content:space-between;margin-bottom:14px">
      <div>
        <a href="<?php echo base_url('admin/user.php?id=' . (int)$d['user_id']); ?>">
          <strong><?php echo e($d['first_name'] . ' ' . $d['last_name']); ?></strong></a>
        <br><small class="ref"><?php echo e($d['account_number']); ?> · uploaded <?php echo nice_date($d['created_at']); ?></small>
      </div>
      <?php echo status_pill($d['status']); ?>
    </div>

    <div class="grid g2" style="gap:18px">
      <dl class="kv">
        <dt>Document</dt><dd style="font-family:var(--sans)"><?php echo e($d['doc_type']); ?></dd>
        <dt>Number</dt><dd><?php echo e($d['id_number'] ? $d['id_number'] : '—'); ?></dd>
        <dt>On file: date of birth</dt><dd><?php echo e($d['dob'] ? $d['dob'] : '—'); ?></dd>
        <dt>On file: address</dt><dd style="font-family:var(--sans)"><?php echo e(trim($d['address'] . ' ' . $d['city'] . ' ' . $d['country'])); ?></dd>
        <dt>Email</dt><dd style="font-family:var(--sans)"><?php echo e($d['email']); ?></dd>
        <dt>Files</dt><dd>
          <a href="<?php echo base_url($d['front']); ?>" target="_blank" rel="noopener">Front</a>
          <?php if ($d['back']) { ?> · <a href="<?php echo base_url($d['back']); ?>" target="_blank" rel="noopener">Back</a><?php } ?>
        </dd>
        <?php if ($d['note']) { ?><dt>Note</dt><dd style="font-family:var(--sans)"><?php echo e($d['note']); ?></dd><?php } ?>
      </dl>

      <?php if ($d['status'] === 'pending') { ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
        <div class="field"><label>Note <span class="muted">(shown to the customer)</span></label>
          <textarea name="note" rows="3" placeholder="Photo was blurred — please resend."></textarea></div>
        <input type="hidden" name="decision_fallback" value="">
        <div class="flexbar">
          <button class="btn btn-primary" type="submit" name="decision" value="approve" onclick="if(this.form.decision_fallback)this.form.decision_fallback.value='approve';"
            data-confirm="Mark this customer as verified?">Approve</button>
          <button class="btn btn-danger" type="submit" name="decision" value="decline" onclick="if(this.form.decision_fallback)this.form.decision_fallback.value='decline';">Decline</button>
        </div>
      </form>
      <?php } else { ?>
        <p class="small muted">Reviewed already. Change the customer's status from their profile if needed.</p>
      <?php } ?>
    </div>
  </section>
<?php } ?>
</div>
<?php } ?>

<?php layout_foot('admin');
