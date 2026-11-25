<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$brand = setting('site_name', 'Evergreen Capital');
$sent  = get('sent') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = post('name');
    $email = post('email');
    $errs  = array();
    if ($name === '') $errs[] = 'Tell us your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Enter a valid email address.';
    if (post('message') === '') $errs[] = 'Write your message.';

    if ($errs) {
        flash('error', implode(' ', $errs));
    } else {
        save_enquiry('contact', $name, $email, post('phone'),
            post('topic', 'General enquiry'), post('message'),
            post('account') !== '' ? 'Account reference: ' . post('account') : '');
        flash('success', 'Message received. We reply within one working day.');
        redirect('contact.php?sent=1');
    }
}

site_head('Contact us', 'Talk to a person at ' . $brand . '. We reply within one working day.', 'contact.php');
?>

<div class="site">
  <section class="sec">
    <div class="split reveal">
      <div>
        <div class="rule"></div>
        <h1 style="font:400 42px/1.12 var(--serif);color:var(--ink-2);margin-bottom:14px">Talk to a person</h1>
        <p style="font-size:16.5px;color:var(--muted);margin-bottom:28px">
          No phone tree and no ticket number you have to quote back at us. Write to us and
          somebody who can actually help will reply.</p>

        <?php
        $details = array_filter(array(
            'Email'   => setting('support_email'),
            'Phone'   => setting('support_phone'),
            'Address' => setting('address'),
        ));
        if ($details) {
            echo '<dl class="kv" style="margin-bottom:26px">';
            foreach ($details as $k => $v)
                echo '<dt>' . e($k) . '</dt><dd style="font-family:var(--sans);text-align:right">' . e($v) . '</dd>';
            echo '<dt>Hours</dt><dd style="font-family:var(--sans)">Mon–Fri, 8am–6pm</dd></dl>';
        }
        ?>

        <div class="card">
          <span class="eyebrow">Careful</span>
          <h2 style="font-size:16px;margin-bottom:6px">We will never ask for your PIN</h2>
          <p class="small muted mb0">Nobody from <?php echo e($brand); ?> will ever ask for your
             password, transfer PIN or one-time code, by phone, email or message. If someone does,
             it is not us — hang up and tell us.</p>
        </div>

        <div class="card" style="margin-top:16px">
          <span class="eyebrow">Already a customer?</span>
          <p class="small muted" style="margin-bottom:12px">Signing in first means we can see your
             account and answer properly.</p>
          <a class="btn btn-sm btn-primary" href="<?php echo base_url('login.php'); ?>">Sign in</a>
        </div>
      </div>

      <div class="card">
        <?php echo render_flash(); ?>
        <?php if ($sent) { ?>
          <div class="empty" style="padding:36px 10px">
            <h3>Message sent</h3>
            <p>Thanks — we have it, and a copy is on its way to your inbox. Expect a reply
               within one working day.</p>
            <a class="btn" href="<?php echo base_url('index.php'); ?>">Back to the site</a>
          </div>
        <?php } else { ?>
        <h2>Send us a message</h2>
        <p class="sub">Everything here reaches a real inbox.</p>
        <form method="post">
          <?php echo csrf_field(); ?>
          <div class="row2">
            <div class="field"><label for="n">Your name</label>
              <input type="text" id="n" name="name" required></div>
            <div class="field"><label for="p">Phone</label>
              <input type="tel" id="p" name="phone"></div>
          </div>
          <div class="field"><label for="em">Email</label>
            <input type="email" id="em" name="email" required></div>
          <div class="field"><label for="t">What is it about?</label>
            <select id="t" name="topic">
              <option>General enquiry</option>
              <option>Opening an account</option>
              <option>A payment or transfer</option>
              <option>Loans and borrowing</option>
              <option>Cards</option>
              <option>Something went wrong</option>
              <option>Press or partnerships</option>
            </select></div>
          <div class="field"><label for="ac">Account number <span class="muted">(if you have one)</span></label>
            <input type="text" id="ac" name="account" class="mono"></div>
          <div class="field"><label for="m">Message</label>
            <textarea id="m" name="message" rows="6" required></textarea></div>
          <button class="btn btn-primary btn-block" type="submit" data-busy-text="Sending…">Send message</button>
          <p class="small muted" style="margin:12px 0 0">Never include your password, PIN or a one-time code in a message.</p>
        </form>
        <?php } ?>
      </div>
    </div>
  </section>
</div>

<?php site_foot();
