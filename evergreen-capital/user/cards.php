<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'request') {
        if (setting('cards_enabled', '1') !== '1') {
            flash('error', 'Card issuing is paused.');
            redirect('user/cards.php');
        }
        $fee = (float)setting('card_fee', '0');
        if ($fee > 0 && $fee > (float)$u['balance']) {
            flash('error', 'The issuing fee is ' . money($fee) . ' and your balance is short.');
            redirect('user/cards.php');
        }
        $brand = post('brand', 'Visa');
        $type  = post('card_type', 'Virtual');
        db()->prepare('INSERT INTO cards (user_id, card_type, brand, number, cvv, expiry, card_pin, spend_limit, status, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute(array($u['id'], $type, $brand, generate_card_number($brand),
                str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT),
                date('m/y', strtotime('+4 years')),
                password_hash(post('pin', '0000'), PASSWORD_DEFAULT),
                amount_in('spend_limit'), 'pending', now()));

        if ($fee > 0) {
            post_transaction($u['id'], 'debit', 'charge', $fee, 'Card issuing fee', array('channel' => 'system'));
        }

        notify_user($u, 'Card requested', 'Card requested',
            '<p>Your ' . e($type) . ' ' . e($brand) . ' card is with our issuing team. We will email you when it is live.</p>');
        foreach (db()->query("SELECT email FROM users WHERE role='admin'")->fetchAll() as $a) {
            send_mail($a['email'], 'Card request — ' . user_name($u), mail_template('Card request',
                '<p><strong>' . e(user_name($u)) . '</strong> requested a ' . e($type) . ' ' . e($brand) . ' card.</p>',
                'Review cards', base_url('admin/cards.php')));
        }
        flash('success', 'Card requested. It stays inactive until the bank approves it.');
        redirect('user/cards.php');
    }

    if ($action === 'freeze' || $action === 'unfreeze') {
        $id = (int)post('card_id');
        $new = $action === 'freeze' ? 'frozen' : 'approved';
        db()->prepare('UPDATE cards SET status = ? WHERE id = ? AND user_id = ?')
            ->execute(array($new, $id, $u['id']));
        flash('success', $action === 'freeze' ? 'Card frozen. No new spending will go through.' : 'Card is active again.');
        redirect('user/cards.php');
    }
}

$st = db()->prepare('SELECT * FROM cards WHERE user_id = ? ORDER BY id DESC');
$st->execute(array($u['id']));
$cards = $st->fetchAll();

layout_head('Cards', 'user');
page_title('Cards', 'Request a card, freeze it in a tap, and set your own spending ceiling.');
?>

<div class="grid g-main" style="align-items:start">
  <div class="stack">
    <?php if (!$cards) { ?>
      <section class="card"><div class="empty">
        <img src="<?php echo base_url('assets/img/empty-cards.svg'); ?>" alt="" width="200" height="120" class="empty-art">
        <h3>No cards yet</h3>
        <p>A virtual card works online straight away. A physical card is posted to your address on file.</p>
      </div></section>
    <?php } foreach ($cards as $c) { ?>
      <section class="card">
        <div class="grid g2" style="align-items:center">
          <div class="cardface<?php echo $c['status'] !== 'approved' ? ' frozen' : ''; ?>" style="max-width:none">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
              <span class="cbrand"><?php echo e(setting('site_name')); ?></span>
              <span style="color:#88A497;font-size:11px;letter-spacing:.12em;text-transform:uppercase"><?php echo e($c['card_type']); ?></span>
            </div>
            <div class="chip"></div>
            <div class="cnum" id="num<?php echo (int)$c['id']; ?>"
                 data-masked="<?php echo e(mask_card($c['number'])); ?>"
                 data-full="<?php echo e(group_card($c['number'])); ?>" data-shown="0"><?php echo e(mask_card($c['number'])); ?></div>
            <div class="cfoot">
              <div><span>Card holder</span><b><?php echo e(strtoupper(user_name($u))); ?></b></div>
              <div><span>Expires</span><b><?php echo e($c['expiry']); ?></b></div>
              <div><span>CVV</span><b><?php echo $c['status'] === 'approved' ? e($c['cvv']) : '•••'; ?></b></div>
            </div>
          </div>

          <div>
            <div class="flexbar" style="justify-content:space-between;margin-bottom:12px">
              <strong><?php echo e($c['brand']); ?> · <?php echo e($c['card_type']); ?></strong>
              <?php echo status_pill($c['status']); ?>
            </div>
            <dl class="kv" style="margin-bottom:16px">
              <dt>Spending limit</dt><dd><?php echo (float)$c['spend_limit'] > 0 ? money($c['spend_limit']) : 'No limit'; ?></dd>
              <dt>Requested</dt><dd><?php echo nice_date($c['created_at'], false); ?></dd>
            </dl>
            <?php if ($c['admin_note']) { ?>
              <p class="small muted">Note from the bank: <?php echo e($c['admin_note']); ?></p>
            <?php } ?>
            <div class="flexbar">
              <?php if ($c['status'] === 'approved') { ?>
                <a href="#" class="btn btn-sm" data-reveal="num<?php echo (int)$c['id']; ?>">Show details</a>
                <form method="post" style="display:inline">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="freeze">
                  <input type="hidden" name="card_id" value="<?php echo (int)$c['id']; ?>">
                  <button class="btn btn-sm btn-danger" type="submit" data-confirm="Freeze this card?">Freeze card</button>
                </form>
              <?php } elseif ($c['status'] === 'frozen') { ?>
                <form method="post" style="display:inline">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="unfreeze">
                  <input type="hidden" name="card_id" value="<?php echo (int)$c['id']; ?>">
                  <button class="btn btn-sm btn-primary" type="submit">Unfreeze</button>
                </form>
              <?php } else { ?>
                <span class="small muted">Waiting on the issuing team.</span>
              <?php } ?>
            </div>
          </div>
        </div>
      </section>
    <?php } ?>
  </div>

  <section class="card">
    <span class="eyebrow">New card</span>
    <h2>Request a card</h2>
    <p class="sub"><?php $f = (float)setting('card_fee', '0');
      echo $f > 0 ? 'Issuing fee ' . money($f) . ', taken on request.' : 'No issuing fee.'; ?></p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="request">
      <div class="field"><label for="ct">Format</label>
        <select id="ct" name="card_type"><option>Virtual</option><option>Physical</option></select></div>
      <div class="field"><label for="br">Network</label>
        <select id="br" name="brand"><option>Visa</option><option>Mastercard</option></select></div>
      <div class="field"><label for="sl">Monthly spending limit <span class="muted">(0 for none)</span></label>
        <div class="money-in"><span><?php echo e(cur()); ?></span>
          <input type="text" id="sl" name="spend_limit" inputmode="decimal" value="0"></div></div>
      <div class="field pinbox"><label for="cpin">Choose a card PIN</label>
        <input type="password" id="cpin" name="pin" maxlength="4" inputmode="numeric" required></div>
      <button class="btn btn-primary btn-block" type="submit">Request card</button>
    </form>
  </section>
</div>

<?php layout_foot('user');
