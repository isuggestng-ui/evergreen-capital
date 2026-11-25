<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

if (get('read')) {
    db()->prepare('UPDATE messages SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute(array((int)get('read'), $u['id']));
    redirect('user/messages.php?open=' . (int)get('read'));
}
if (get('readall') === '1') {
    db()->prepare('UPDATE messages SET is_read = 1 WHERE user_id = ?')->execute(array($u['id']));
    flash('success', 'Inbox cleared.');
    redirect('user/messages.php');
}

$st = db()->prepare('SELECT * FROM messages WHERE user_id = ? OR user_id IS NULL ORDER BY id DESC LIMIT 60');
$st->execute(array($u['id']));
$rows = $st->fetchAll();

$open = null;
if (get('open')) {
    foreach ($rows as $r) if ((int)$r['id'] === (int)get('open')) $open = $r;
}

layout_head('Inbox', 'user');
page_title('Inbox', 'Notices from the bank about your account, transfers and applications.',
    '<a class="btn" href="' . base_url('user/messages.php?readall=1') . '">Mark all read</a>');
?>

<div class="grid g-main" style="align-items:start">
  <section class="card card-flush">
    <div class="card-head"><h2>Messages</h2></div>
    <?php if (!$rows) { ?>
      <div class="empty"><img src="<?php echo base_url('assets/img/empty-inbox.svg'); ?>" alt="" width="180" height="120" class="empty-art"><h3>Nothing here</h3><p>Account notices land in this inbox and in your email.</p></div>
    <?php } else { ?>
      <div class="tablewrap"><table class="tbl"><tbody>
      <?php foreach ($rows as $m) { ?>
        <tr<?php echo $open && (int)$open['id'] === (int)$m['id'] ? ' style="background:#F4F7F5"' : ''; ?>>
          <td>
            <a href="<?php echo base_url('user/messages.php?read=' . (int)$m['id']); ?>">
              <strong style="<?php echo (int)$m['is_read'] === 0 ? 'font-weight:700' : 'font-weight:500'; ?>">
                <?php echo e($m['subject']); ?></strong></a>
            <?php if ((int)$m['is_read'] === 0 && $m['user_id'] !== null) echo ' <span class="pill pill-wait">New</span>'; ?>
            <br><small class="muted"><?php echo nice_date($m['created_at']); ?><?php echo $m['user_id'] === null ? ' · Sent to all customers' : ''; ?></small>
          </td>
        </tr>
      <?php } ?>
      </tbody></table></div>
    <?php } ?>
  </section>

  <section class="card">
    <?php if ($open) { ?>
      <span class="eyebrow"><?php echo nice_date($open['created_at']); ?></span>
      <h2 style="font:400 23px/1.25 var(--serif);margin-bottom:12px"><?php echo e($open['subject']); ?></h2>
      <div class="small" style="line-height:1.7">
        <?php echo strip_tags($open['body'], '<p><br><strong><em><ul><ol><li><a><ic>'); ?>
      </div>
    <?php } else { ?>
      <div class="empty" style="padding:30px 10px">
        <h3>Pick a message</h3><p>Select anything on the left to read it here.</p>
      </div>
    <?php } ?>
  </section>
</div>

<?php layout_foot('user');
