<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

$q      = get('q');
$action = get('action');
$page   = max(1, (int)get('p', 1));
$per    = 40;

$where = '1=1';
$args  = array();
if ($q !== '') {
    $where .= " AND (actor_name " . db_like() . " ? OR details " . db_like() . " ? OR ip " . db_like() . " ?)";
    for ($i = 0; $i < 3; $i++) $args[] = '%' . $q . '%';
}
if ($action !== '') { $where .= ' AND action = ?'; $args[] = $action; }

$total  = count_where('audit_log', $where, $args);
$pages  = max(1, (int)ceil($total / $per));
$page   = min($page, $pages);
$offset = ($page - 1) * $per;

$st = db()->prepare("SELECT * FROM audit_log WHERE $where ORDER BY id DESC LIMIT $per OFFSET $offset");
$st->execute($args);
$rows = $st->fetchAll();

$actions = db()->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll();

layout_head('Audit log', 'admin');
page_title('Audit log', $total . ' recorded action' . ($total === 1 ? '' : 's') . '. Every staff decision leaves a trace here.');
?>

<section class="card" style="margin-bottom:18px">
  <form method="get" class="flexbar" style="align-items:end;gap:12px">
    <div class="field mb0" style="flex:2;min-width:200px"><label>Search</label>
      <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Staff name, detail, IP address"></div>
    <div class="field mb0" style="min-width:190px"><label>Action</label>
      <select name="action">
        <option value="">Any action</option>
        <?php foreach ($actions as $a) {
          echo '<option value="' . e($a['action']) . '"' . ($action === $a['action'] ? ' selected' : '') . '>'
             . e(ucfirst(str_replace('_', ' ', $a['action']))) . '</option>';
        } ?>
      </select></div>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn" href="<?php echo base_url('admin/audit.php'); ?>">Clear</a>
  </form>
</section>

<section class="card card-flush">
  <?php if (!$rows) { ?>
    <div class="empty"><h3>Nothing logged yet</h3><p>Sign-ins, approvals and account changes will appear here.</p></div>
  <?php } else { ?>
  <div class="tablewrap"><table class="tbl">
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r) { ?>
      <tr>
        <td class="small nowrap"><?php echo nice_date($r['created_at']); ?></td>
        <td class="small"><?php echo e($r['actor_name']); ?></td>
        <td><span class="pill pill-wait" style="background:var(--line-2);color:var(--ink)">
          <?php echo e(str_replace('_', ' ', $r['action'])); ?></span></td>
        <td class="small">
          <?php echo e($r['details'] ? str_short($r['details'], 60) : '—'); ?>
          <?php if ($r['entity'] === 'user' && $r['entity_id']) { ?>
            · <a href="<?php echo base_url('admin/user.php?id=' . (int)$r['entity_id']); ?>">open customer</a>
          <?php } ?>
        </td>
        <td class="mono small muted"><?php echo e($r['ip']); ?></td>
      </tr>
    <?php } ?>
    </tbody></table></div>
  <?php if ($pages > 1) { ?>
    <div class="flexbar" style="padding:14px 18px;border-top:1px solid var(--line-2)">
      <span class="small muted">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
      <div class="spacer"></div>
      <?php $b = array('q' => $q, 'action' => $action);
      if ($page > 1) echo '<a class="btn btn-sm" href="?' . e(http_build_query(array_merge($b, array('p' => $page - 1)))) . '">Previous</a> ';
      if ($page < $pages) echo '<a class="btn btn-sm" href="?' . e(http_build_query(array_merge($b, array('p' => $page + 1)))) . '">Next</a>'; ?>
    </div>
  <?php } ?>
  <?php } ?>
</section>

<?php layout_foot('admin');
