<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$brand = setting('site_name', 'Evergreen Capital');
site_head('About us', 'Who we are and why we built ' . $brand . '.', 'about.php');
?>

<div class="site">
  <section class="sec">
    <div class="sec-head reveal" style="max-width:70ch">
      <div class="rule"></div>
      <h1 style="font:400 46px/1.1 var(--serif);color:var(--ink-2);margin-bottom:14px">
        A bank should be boring in the right places</h1>
      <p style="font-size:17.5px">Predictable with your money, clear about its charges, and quick
         when you need something. Exciting is for other industries.</p>
    </div>
  </section>

  <section class="sec" style="padding-top:0">
    <div class="split reveal">
      <div class="split-art">
        <img src="<?php echo base_url('assets/img/community.jpg'); ?>"
             alt="Customers using the app" width="1200" height="800" loading="lazy">
      </div>
      <div>
        <div class="rule"></div>
        <h2>Why we started</h2>
        <p>We kept meeting people who had been quietly charged for things nobody explained,
           who found out about a failed transfer days later, and who could not get a statement
           without visiting a branch on a working Tuesday.</p>
        <p>None of that is a technology problem. It is a decision, made repeatedly, to make the
           bank's life easier than the customer's. <?php echo e($brand); ?> is our attempt to
           make the opposite decision every time it comes up.</p>
      </div>
    </div>
  </section>

  <section class="sec">
    <?php sec_head('How we work', 'Four commitments we hold ourselves to, and that you can hold us to.'); ?>
    <div class="vals">
      <article>
        <h3>Every charge is visible first</h3>
        <p>Fees are shown on the screen where the decision is made, not discovered afterwards
           on a statement.</p>
      </article>
      <article>
        <h3>Money is never in two places</h3>
        <p>The moment a payment is submitted, those funds are held. Your available balance
           always reflects what you can actually spend.</p>
      </article>
      <article>
        <h3>A person reviews the big things</h3>
        <p>Outward transfers, loans and identity checks are decided by a human being with a
           name and an audit trail, not silently by a rule.</p>
      </article>
      <article>
        <h3>We write it in English</h3>
        <p>No “pursuant to the applicable schedule”. If we cannot explain a policy plainly,
           we treat that as a sign the policy is wrong.</p>
      </article>
    </div>
  </section>

  <section class="sec">
    <div class="split flip reveal">
      <div class="split-art">
        <img src="<?php echo base_url('assets/img/lending.jpg'); ?>"
             alt="An adviser going through paperwork with customers" width="1200" height="800" loading="lazy">
      </div>
      <div>
        <div class="rule"></div>
        <h2>Lending, decided by people</h2>
        <p>A loan decision changes someone's year. We show the full cost before the application
           is even submitted, and a credit officer reads every file rather than leaving it to a score.</p>
        <p>When we say no, we say why, and we say what would change the answer.</p>
        <a class="btn btn-primary" href="<?php echo base_url('index.php#lending'); ?>">How our loans work</a>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="band reveal">
      <div class="rule"></div>
      <h2>Where we are</h2>
      <?php
      $bits = array_filter(array(setting('site_legal'), setting('address'),
          implode(' · ', array_filter(array(setting('support_phone'), setting('support_email'))))));
      echo $bits
          ? '<p style="max-width:54ch;margin-bottom:26px">' . implode('<br>', array_map('e', $bits)) . '</p>'
          : '<p style="max-width:54ch;margin-bottom:26px">Add your company details under Settings &rsaquo; Identity.</p>';
      ?>
      <div class="flexbar">
        <a class="btn btn-brass" href="<?php echo base_url('careers.php'); ?>">See open roles</a>
        <a class="btn-ghost-light" href="<?php echo base_url('contact.php'); ?>">Get in touch</a>
      </div>
    </div>
  </section>
</div>

<?php site_foot();
