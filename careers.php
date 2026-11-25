<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$brand = setting('site_name', 'Evergreen Capital');

/** Edit this list to change the openings. */
$roles = array(
    array('Senior Backend Engineer', 'Engineering', 'Hybrid — Wilmington, DE', 'Full time',
          'Own the ledger and payments core. Strong PHP or Go, and a real feel for correctness around money.'),
    array('Product Designer', 'Design', 'Remote', 'Full time',
          'Shape the customer app end to end, from the first sketch to the shipped screen.'),
    array('Credit Analyst', 'Lending', 'New York, NY', 'Full time',
          'Review applications, set terms, and help us decide who we can say yes to.'),
    array('Compliance Officer', 'Risk & Compliance', 'Wilmington, DE', 'Full time',
          'KYC, AML monitoring and regulatory reporting. Prior banking experience essential.'),
    array('Customer Support Specialist', 'Operations', 'Wilmington, DE', 'Full time',
          'Answer real people about real money, quickly and in plain language.'),
    array('Growth Marketer', 'Marketing', 'Remote', 'Contract',
          'Own acquisition across paid, organic and partnerships. Comfortable with numbers.'),
);

$applied = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = post('name');
    $email = post('email');
    $role  = post('role');
    $errs  = array();
    if ($name === '') $errs[] = 'Tell us your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Enter a valid email address.';
    if (post('message') === '') $errs[] = 'Write a few lines about yourself.';

    if ($errs) {
        flash('error', implode(' ', $errs));
    } else {
        save_enquiry('application', $name, $email, post('phone'),
            'Application — ' . $role, post('message'),
            'Portfolio or LinkedIn: ' . (post('link') !== '' ? post('link') : 'not supplied'));
        flash('success', 'Application received. We read every one and reply within a week.');
        redirect('careers.php?sent=1');
    }
}
$applied = get('sent') === '1';

site_head('Careers', 'Open roles at ' . $brand . '. Build banking that people actually trust.', 'careers.php');
?>

<div class="site">
  <section class="sec" style="padding-bottom:26px">
    <div class="banner reveal">
      <img src="<?php echo base_url('assets/img/careers-banner.jpg'); ?>"
           alt="The team at work" width="1800" height="720">
      <div class="banner-cap">
        <h1 style="font:400 42px/1.1 var(--serif);color:#F6F3E9;margin-bottom:10px">Come and build it with us</h1>
        <p>We are a small team making money move properly for people who have been let down
           by banks before. If that sounds worth your time, look below.</p>
      </div>
    </div>
  </section>

  <section class="sec" style="padding-top:24px">
    <?php sec_head('What we care about', 'Three things, and we mean them.'); ?>
    <div class="vals reveal">
      <article>
        <h3>Correctness over speed</h3>
        <p>It is money. A feature shipped a week late is forgivable; a balance that is wrong
           by a cent is not. We would rather write the test.</p>
      </article>
      <article>
        <h3>Plain language, always</h3>
        <p>If a customer needs a glossary to understand their own statement, we have failed.
           That standard applies to our writing, our errors and our emails.</p>
      </article>
      <article>
        <h3>Small team, real ownership</h3>
        <p>You will not be the ninth person on a feature. You will own something end to end
           and see it in front of customers within weeks.</p>
      </article>
      <article>
        <h3>Answer the phone</h3>
        <p>Everyone, including engineers, spends time on support each quarter. It is the
           fastest way to learn what we actually broke.</p>
      </article>
    </div>
  </section>

  <section class="sec">
    <?php sec_head('Open roles', count($roles) . ' positions open right now.'); ?>
    <div class="roles reveal">
      <?php foreach ($roles as $r) { ?>
        <div class="role">
          <div style="flex:1;min-width:240px">
            <h3><?php echo e($r[0]); ?></h3>
            <p class="meta" style="margin:0 0 6px"><?php echo e($r[1] . ' · ' . $r[2] . ' · ' . $r[3]); ?></p>
            <p class="meta" style="margin:0;max-width:66ch"><?php echo e($r[4]); ?></p>
          </div>
          <a class="btn btn-primary" href="#apply"
             onclick="var s=document.getElementById('roleSelect'); if(s) s.value='<?php echo e($r[0]); ?>';">Apply</a>
        </div>
      <?php } ?>
    </div>
    <p class="muted small" style="margin-top:18px">
      Nothing that fits? Apply anyway and choose “Open application”. We keep good people on file.
    </p>
  </section>

  <section class="sec">
    <div class="band reveal">
      <div class="rule"></div>
      <h2>What we offer</h2>
      <p style="max-width:56ch;margin-bottom:30px">The usual things done properly, rather than
         a long list of perks nobody uses.</p>
      <div class="fgrid">
        <article><?php echo icon('bank'); ?><h3>Honest pay</h3>
          <p>Benchmarked salary bands, reviewed twice a year, with no negotiation penalty.</p></article>
        <article><?php echo icon('users'); ?><h3>Private health cover</h3>
          <p>For you and your immediate family, from your first day.</p></article>
        <article><?php echo icon('doc'); ?><h3>Learning budget</h3>
          <p>An annual allowance for courses, conferences and books, no approval theatre.</p></article>
      </div>
    </div>
  </section>

  <section class="sec" id="apply">
    <div class="split">
      <div>
        <div class="rule"></div>
        <h2>Apply</h2>
        <p>Tell us which role, and a few honest lines about what you have built. We would
           rather read three real paragraphs than a two-page CV of adjectives.</p>
        <p class="muted small">We reply to every application within a week, including the ones
           we turn down. You can also write to <?php echo e(setting('support_email')); ?>.</p>
      </div>
      <div class="card">
        <?php echo render_flash(); ?>
        <?php if ($applied) { ?>
          <div class="empty" style="padding:30px 10px">
            <h3>Thank you</h3>
            <p>Your application is with us. Check your email for the acknowledgement.</p>
            <a class="btn" href="<?php echo base_url('careers.php'); ?>">Apply for another role</a>
          </div>
        <?php } else { ?>
        <form method="post">
          <?php echo csrf_field(); ?>
          <div class="field"><label for="role">Role</label>
            <select id="roleSelect" name="role">
              <?php foreach ($roles as $r) echo '<option>' . e($r[0]) . '</option>'; ?>
              <option>Open application</option>
            </select></div>
          <div class="row2">
            <div class="field"><label for="n">Your name</label>
              <input type="text" id="n" name="name" required></div>
            <div class="field"><label for="p">Phone</label>
              <input type="tel" id="p" name="phone"></div>
          </div>
          <div class="field"><label for="em">Email</label>
            <input type="email" id="em" name="email" required></div>
          <div class="field"><label for="lk">Portfolio, GitHub or LinkedIn</label>
            <input type="text" id="lk" name="link" placeholder="https://"></div>
          <div class="field"><label for="msg">Why you, for this role?</label>
            <textarea id="msg" name="message" rows="6" required
              placeholder="What have you built, and what part of it are you proud of?"></textarea></div>
          <button class="btn btn-primary btn-block" type="submit" data-busy-text="Sending…">Send application</button>
        </form>
        <?php } ?>
      </div>
    </div>
  </section>
</div>

<?php site_foot();
