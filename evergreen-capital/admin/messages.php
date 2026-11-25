<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'send') {
        $subject = post('subject');
        $body    = post('body');
        $to      = post('to', 'one');
        $sendEmail = post('send_email') === '1';

        if ($subject === '' || $body === '') {
            flash('error', 'A message needs a subject and a body.');
            redirect('admin/messages.php');
        }

        $html = '<p>' . nl2br(e($body)) . '</p>';
        $sent = 0;

        if ($to === 'all') {
            // One broadcast row (user_id NULL) so every customer sees it in their inbox.
            db()->prepare('INSERT INTO messages (user_id, subject, body, created_at) VALUES (NULL,?,?,?)')
                ->execute(array($subject, $html, now()));
            if ($sendEmail) {
                foreach (db()->query("SELECT * FROM users WHERE role='user' AND status='active'")->fetchAll() as $c) {
                    if (send_mail($c['email'], $subject, mail_template($subject, $html))) $sent++;
                }
            }
            audit('broadcast', 'message', 0, $subject);
            flash('success', 'Broadcast posted to every customer inbox' . ($sendEmail ? ' and emailed to ' . $sent . '.' : '.'));
        } else {
            $cust = find_user((int)post('user_id'));
            if (!$cust) {
                flash('error', 'Pick a customer to write to.');
                redirect('admin/messages.php');
            }
            notify_user($cust, $subject, $subject, $html, '', '', true);
            audit('message_sent', 'message', $cust['id'], $subject);
            flash('success', 'Message sent to ' . user_name($cust) . '.');
        }
        redirect('admin/messages.php');
    }

    if (post('action') === 'delete') {
        db()->prepare('DELETE FROM messages WHERE id = ?')->execute(array((int)post('id')));
        flash('success', 'Message deleted.');
        redirect('admin/messages.php');
    }
}

$customers = db()->query("SELECT id, first_name, last_name, email, account_number
                          FROM users WHERE role='user' ORDER BY first_name")->fetchAll();

$sentRows = db()->query('SELECT m.*, u.first_name, u.last_name
                         FROM messages m LEFT JOIN users u ON u.id = m.user_id
                         ORDER BY m.id DESC LIMIT 40')->fetchAll();

layout_head('Messaging', 'admin');
page_title('Messaging', 'Write to one customer or post a notice to everyone at once.');
?>

<div class="grid g-main" style="align-items:start">
  <section class="card">
    <span class="eyebrow">Compose</span>
    <h2>New message</h2>
    <p class="sub">Messages land in the customer's inbox. Tick the box to email them as well.</p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="send">

      <div class="field">
        <label for="to">Send to</label>
        <select id="to" name="to" onchange="document.getElementById('pick').hidden = (this.value === 'all')">
          <option value="one">One customer</option>
          <option value="all">Every customer</option>
        </select>
      </div>

      <div class="field" id="pick">
        <label for="uid">Customer</label>
        <select id="uid" name="user_id">
          <option value="">Choose someone…</option>
          <?php foreach ($customers as $c) { ?>
            <option value="<?php echo (int)$c['id']; ?>"<?php echo (int)get('user') === (int)$c['id'] ? ' selected' : ''; ?>>
              <?php echo e($c['first_name'] . ' ' . $c['last_name'] . ' — ' . $c['account_number']); ?>
            </option>
          <?php } ?>
        </select>
      </div>

      <div class="field"><label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" maxlength="180" required
          placeholder="Scheduled maintenance this Sunday"></div>

      <div class="field"><label for="body">Message</label>
        <textarea id="body" name="body" rows="8" required
          placeholder="Write in plain language. Line breaks are preserved."></textarea></div>

      <label class="check"><input type="checkbox" name="send_email" value="1" checked>
        <span>Also send it by email</span></label>

      <button class="btn btn-primary" type="submit" data-busy-text="Sending…">Send message</button>
      <p class="small muted" style="margin:12px 0 0">Broadcasting to every customer can take a while if email is on and you have many accounts.</p>
    </form>
  </section>

  <section class="card card-flush">
    <div class="card-head"><h2>Recently sent</h2></div>
    <?php if (!$sentRows) { ?>
      <div class="empty"><h3>No messages yet</h3><p>Anything you or the system sends will be listed here.</p></div>
    <?php } else { ?>
    <div class="tablewrap"><table class="tbl">
      <thead><tr><th>Subject</th><th>To</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($sentRows as $m) { ?>
        <tr>
          <td><strong><?php echo e(str_short($m['subject'], 42)); ?></strong><br>
            <small class="muted"><?php echo nice_date($m['created_at']); ?></small></td>
          <td class="small">
            <?php echo $m['user_id'] === null
                ? '<span class="pill pill-wait">Everyone</span>'
                : e($m['first_name'] . ' ' . $m['last_name']); ?>
          </td>
          <td class="right">
            <form method="post" style="display:inline">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
              <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this message?">Delete</button>
            </form>
          </td>
        </tr>
      <?php } ?>
      </tbody></table></div>
    <?php } ?>
  </section>
</div>

<?php layout_foot('admin');
