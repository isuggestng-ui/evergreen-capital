<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'add') {
        if (post('name') === '' || post('account_number') === '') {
            flash('error', 'A beneficiary needs at least a name and an account number.');
        } else {
            db()->prepare('INSERT INTO beneficiaries (user_id,name,account_number,bank_name,swift,country,kind,created_at)
                           VALUES (?,?,?,?,?,?,?,?)')
                ->execute(array($u['id'], post('name'), post('account_number'), post('bank_name'),
                    post('swift'), post('country'), post('kind', 'domestic'), now()));
            flash('success', post('name') . ' saved.');
        }
    }
    if (post('action') === 'delete') {
        db()->prepare('DELETE FROM beneficiaries WHERE id = ? AND user_id = ?')
            ->execute(array((int)post('id'), $u['id']));
        flash('success', 'Beneficiary removed.');
    }
    redirect('user/beneficiaries.php');
}

$st = db()->prepare('SELECT * FROM beneficiaries WHERE user_id = ? ORDER BY name');
$st->execute(array($u['id']));
$rows = $st->fetchAll();

layout_head('Beneficiaries', 'user');
page_title('Beneficiaries', 'People and accounts you send to often. Saved here, they fill the transfer form in one click.');
?>

<div class="grid g-main" style="align-items:start">
  <section class="card card-flush">
    <div class="card-head"><h2>Saved beneficiaries</h2><span class="small muted"><?php echo count($rows); ?> saved</span></div>
    <?php if (!$rows) { ?>
      <div class="empty"><img src="<?php echo base_url('assets/img/empty-inbox.svg'); ?>" alt="" width="180" height="120" class="empty-art"><h3>Nobody saved yet</h3><p>Add the people you pay regularly and skip retyping account numbers.</p></div>
    <?php } else { ?>
    <div class="tablewrap"><table class="tbl">
      <thead><tr><th>Name</th><th>Account</th><th>Bank</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $b) { ?>
        <tr>
          <td><strong><?php echo e($b['name']); ?></strong><br>
            <small class="muted"><?php echo e(ucfirst($b['kind'])); ?><?php echo $b['country'] ? ' · ' . e($b['country']) : ''; ?></small></td>
          <td class="mono small"><?php echo e($b['account_number']); ?><?php echo $b['swift'] ? '<br><span class="muted">' . e($b['swift']) . '</span>' : ''; ?></td>
          <td class="small"><?php echo e($b['bank_name']); ?></td>
          <td class="right nowrap">
            <a class="btn btn-sm" href="<?php echo base_url('user/transfer.php'); ?>">Send</a>
            <form method="post" style="display:inline">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
              <button class="btn btn-sm btn-danger" type="submit" data-confirm="Remove <?php echo e($b['name']); ?>?">Remove</button>
            </form>
          </td>
        </tr>
      <?php } ?>
      </tbody></table></div>
    <?php } ?>
  </section>

  <section class="card">
    <span class="eyebrow">Add someone</span>
    <h2>New beneficiary</h2>
    <p class="sub">Saving a beneficiary does not move any money.</p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add">
      <div class="field"><label for="n">Name</label><input type="text" id="n" name="name" required></div>
      <div class="field"><label for="an">Account number</label><input type="text" id="an" name="account_number" required></div>
      <div class="field"><label for="bn">Bank</label><input type="text" id="bn" name="bank_name"></div>
      <div class="field"><label for="k">Type</label>
        <select id="k" name="kind">
          <option value="internal">Inside <?php echo e(setting('site_name')); ?></option>
          <option value="domestic">Another local bank</option>
          <option value="wire">International</option>
        </select></div>
      <div class="row2">
        <div class="field"><label for="sw">SWIFT / BIC</label><input type="text" id="sw" name="swift"></div>
        <div class="field"><label for="cy">Country</label><input type="text" id="cy" name="country"></div>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Save beneficiary</button>
    </form>
  </section>
</div>

<?php layout_foot('user');
