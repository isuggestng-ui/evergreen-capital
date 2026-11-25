<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();
ensure_enquiries_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');

    if (post('action') === 'status') {
        db()->prepare('UPDATE enquiries SET status = ?, admin_note = ? WHERE id = ?')
            ->execute(array(post('status'), post('admin_note'), $id));
        audit('enquiry_' . post('status'), 'enquiry', $id);
        flash('success', 'Enquiry marked as ' . post('status') . '.');
    }

    if (post('action') === 'reply') {
        $st = db()->prepare('SELECT * FROM enquiries WHERE id = ?');
        $st->execute(array($id));
        $q = $st->fetch();
        if ($q && post('body') !== '') {
            $ok = send_mail($q['email'], 'Re: ' . $q['subject'],
                mail_template('Re: ' . $q['subject'], '<p>' . nl2br(e(post('body'))) . '</p>'));
            db()->prepare("UPDATE enquiries SET status = 'answered', admin_note = ? WHERE id = ?")
                ->execute(array(post('body'), $id));
            audit('enquiry_replied', 'enquiry', $id);
            flash($ok ? 'success' : 'error', $ok
                ? 'Reply sent to ' . $q['email'] . '.'
                : 'The reply could not be emailed. Check your mail settings.');
        }
    }

    if (post('action') === 'delete') {
        db()->prepare('DELETE FROM enquiries WHERE id = ?')->execute(array($id));
        flash('success', 'Enquiry deleted.');
    }
    redirect('admin/enquiries.php?kind=' . urlencode(get('kind', 'all')) . '&status=' . urlencode(get('status', 'new')));
}

$kind   = get('kind', 'all');
$status = get('status', 'new');

$where = '1=1';
$args  = array();
if ($kind !== 'all') { $where .= ' AND kind = ?'; $args[] = $kind; }
if ($status !== 'all') { $where .= ' AND status = ?'; $args[] = $status; }

$st = db()->prepare("SELECT * FROM enquiries WHERE $where ORDER BY id DESC LIMIT 100");
$st->execute($args);
$rows = $st->fetchAll();

layout_head('Enquiries', 'admin');
page_title('Enquiries', 'Messages from the contact form and applications from the careers page.');
?>

<div class="flexbar" style="margin-bottom:14px">
  <span class="small muted">Type</span>
  <?php foreach (array('all' => 'Everything', 'contact' => 'Contact form', 'application' => 'Job applications') as $k => $label) {
    echo '<a class="btn btn-sm' . ($kind === $k ? ' btn-primary' : '') . '" href="'
       . base_url('admin/enquiries.php?kind=' . $k . '&status=' . e($status)) . '">' . $label . '</a>';
  } ?>
</div>
<div class="flexbar" style="margin-bottom:18px">
  <span class="small muted">Status</span>
  <?php foreach (array('new' => 'New', 'answered' => 'Answered', 'closed' => 'Closed', 'all' => 'Any') as $k => $label) {
    echo '<a class="btn btn-sm' . ($status === $k ? ' btn-primary' : '') . '" href="'
       . base_url('admin/enquiries.php?kind=' . e($kind) . '&status=' . $k) . '">' . $label . '</a>';
  } ?>
</div>

<?php if (!$rows) { ?>
  <section class="card"><div class="empty">
    <img src="<?php echo base_url('assets/img/empty-inbox.svg'); ?>" alt="" width="180" height="120" style="margin-bottom:14px">
    <h3>Nothing here</h3>
    <p>Messages sent through the website's contact and careers forms land in this queue.</p>
  </div></section>
<?php } else { ?>

<div class="stack">
<?php foreach ($rows as $q) { ?>
  <section class="card">
    <div class="flexbar" style="justify-content:space-between;align-items:flex-start;margin-bottom:14px">
      <div>
        <span class="eyebrow" style="margin-bottom:4px">
          <?php echo $q['kind'] === 'application' ? 'Job application' : 'Contact form'; ?> ·
          <?php echo nice_date($q['created_at']); ?></span>
        <h2 style="font-size:19px"><?php echo e($q['subject']); ?></h2>
        <p class="small muted" style="margin:4px 0 0">
          <?php echo e($q['name']); ?> ·
          <a href="mailto:<?php echo e($q['email']); ?>"><?php echo e($q['email']); ?></a>
          <?php echo $q['phone'] ? ' · ' . e($q['phone']) : ''; ?>
        </p>
      </div>
      <?php echo status_pill($q['status'] === 'new' ? 'pending' : ($q['status'] === 'answered' ? 'completed' : 'declined')); ?>
    </div>

    <p style="white-space:pre-line;font-size:14.8px;max-width:80ch"><?php echo e($q['message']); ?></p>
    <?php if ($q['meta']) { ?><p class="small muted"><?php echo e($q['meta']); ?></p><?php } ?>

    <div class="divider"></div>
    <div class="grid g2" style="gap:16px">
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="id" value="<?php echo (int)$q['id']; ?>">
        <div class="field mb0" style="margin-bottom:10px">
          <label>Reply by email</label>
          <textarea name="body" rows="4" placeholder="Write your reply — it goes straight to <?php echo e($q['email']); ?>"><?php echo e($q['admin_note']); ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit" data-busy-text="Sending…">Send reply</button>
      </form>

      <div>
        <form method="post" class="flexbar" style="gap:10px;margin-bottom:10px">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="id" value="<?php echo (int)$q['id']; ?>">
          <select name="status" style="max-width:170px">
            <?php foreach (array('new', 'answered', 'closed') as $s)
              echo '<option value="' . $s . '"' . ($q['status'] === $s ? ' selected' : '') . '>' . ucfirst($s) . '</option>'; ?>
          </select>
          <input type="hidden" name="admin_note" value="<?php echo e($q['admin_note']); ?>">
          <button class="btn btn-sm" type="submit">Update status</button>
        </form>
        <form method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?php echo (int)$q['id']; ?>">
          <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this enquiry?">Delete</button>
        </form>
      </div>
    </div>
  </section>
<?php } ?>
</div>
<?php } ?>

<?php layout_foot('admin');
