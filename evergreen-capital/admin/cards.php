<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');
    $st = db()->prepare('SELECT * FROM cards WHERE id = ?');
    $st->execute(array($id));
    $c = $st->fetch();

    if ($c) {
        $cust = find_user($c['user_id']);
        $status = post('status');
        $note = post('admin_note');
        $limit = post('spend_limit') !== '' ? amount_in('spend_limit') : $c['spend_limit'];

        db()->prepare('UPDATE cards SET status = ?, admin_note = ?, spend_limit = ? WHERE id = ?')
            ->execute(array($status, $note, $limit, $id));
        audit('card_' . $status, 'card', $id);

        if ($status === 'approved' && $c['status'] !== 'approved') {
            notify_user($cust, 'Your card is live', 'Card approved',
                '<p>Your ' . e($c['card_type']) . ' ' . e($c['brand']) . ' card ending ' . e(substr($c['number'], -4)) . ' is active.</p>
                 <p>' . ((float)$limit > 0 ? 'Spending limit: ' . money($limit) : 'No spending limit set.') . '</p>',
                'View my cards', base_url('user/cards.php'));
        } elseif ($status === 'declined') {
            notify_user($cust, 'Card request declined', 'Card request declined',
                '<p>We could not issue that card' . ($note ? ': ' . e($note) : '.') . '</p>');
        }
        flash('success', 'Card updated.');
    }
    redirect('admin/cards.php?filter=' . urlencode(get('filter', 'pending')));
}

$filter = get('filter', 'pending');
$where  = $filter === 'all' ? '1=1' : 'c.status = ?';
$args   = $filter === 'all' ? array() : array($filter);

$st = db()->prepare("SELECT c.*, u.first_name, u.last_name, u.account_number
                     FROM cards c JOIN users u ON u.id = c.user_id
                     WHERE $where ORDER BY c.id DESC LIMIT 100");
$st->execute($args);
$rows = $st->fetchAll();

layout_head('Cards', 'admin');
page_title('Cards', 'Approve requests, set limits, and freeze anything that looks wrong.');
?>

<div class="flexbar" style="margin-bottom:16px">
  <?php foreach (array('pending' => 'Requests', 'approved' => 'Active', 'frozen' => 'Frozen', 'declined' => 'Declined', 'all' => 'Everything') as $k => $label) {
    $n = $k === 'all' ? '' : count_where('cards', 'status = ?', array($k));
    echo '<a class="btn btn-sm' . ($filter === $k ? ' btn-primary' : '') . '" href="'
       . base_url('admin/cards.php?filter=' . $k) . '">' . $label . ($n !== '' && $n > 0 ? ' (' . $n . ')' : '') . '</a>';
  } ?>
</div>

<?php if (!$rows) { ?>
  <section class="card"><div class="empty"><h3>No cards here</h3>
    <p>Nothing matches that filter right now.</p></div></section>
<?php } else { ?>

<div class="grid g2">
<?php foreach ($rows as $c) { ?>
  <section class="card">
    <div class="flexbar" style="justify-content:space-between;margin-bottom:14px">
      <div>
        <a href="<?php echo base_url('admin/user.php?id=' . (int)$c['user_id']); ?>">
          <strong><?php echo e($c['first_name'] . ' ' . $c['last_name']); ?></strong></a>
        <br><small class="ref"><?php echo e($c['account_number']); ?> · requested <?php echo nice_date($c['created_at'], false); ?></small>
      </div>
      <?php echo status_pill($c['status']); ?>
    </div>

    <div class="cardface<?php echo $c['status'] !== 'approved' ? ' frozen' : ''; ?>" style="max-width:none;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <span class="cbrand"><?php echo e(setting('site_name')); ?></span>
        <span style="color:#88A497;font-size:11px;letter-spacing:.12em;text-transform:uppercase"><?php echo e($c['card_type']); ?></span>
      </div>
      <div class="chip"></div>
      <div class="cnum"><?php echo e(group_card($c['number'])); ?></div>
      <div class="cfoot">
        <div><span>Holder</span><b><?php echo e(strtoupper($c['first_name'] . ' ' . $c['last_name'])); ?></b></div>
        <div><span>Expires</span><b><?php echo e($c['expiry']); ?></b></div>
        <div><span>CVV</span><b><?php echo e($c['cvv']); ?></b></div>
      </div>
    </div>

    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
      <div class="row2">
        <div class="field"><label>Status</label>
          <select name="status">
            <?php foreach (array('pending', 'approved', 'frozen', 'declined') as $s)
              echo '<option value="' . $s . '"' . ($c['status'] === $s ? ' selected' : '') . '>' . ucfirst($s) . '</option>'; ?>
          </select></div>
        <div class="field"><label>Spending limit</label>
          <div class="money-in"><span><?php echo e(cur()); ?></span>
            <input type="text" name="spend_limit" value="<?php echo number_format((float)$c['spend_limit'], 2, '.', ''); ?>"></div></div>
      </div>
      <div class="field"><label>Note to the customer</label>
        <input type="text" name="admin_note" value="<?php echo e($c['admin_note']); ?>"></div>
      <button class="btn btn-primary" type="submit">Save card</button>
    </form>
  </section>
<?php } ?>
</div>
<?php } ?>

<?php layout_foot('admin');
