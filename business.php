<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$brand = setting('site_name', 'Evergreen Capital');
site_head('Business banking', 'Accounts, payroll payments and working capital for growing businesses.', 'business.php');
?>

<div class="site">
  <section class="sec">
    <div class="split reveal">
      <div>
        <span class="kicker" style="border-color:var(--line);color:var(--brass);display:inline-flex;align-items:center;gap:8px;margin-bottom:18px;padding:6px 13px;border-radius:20px;border:1px solid var(--line);font:600 10.5px/1 var(--sans);letter-spacing:.15em;text-transform:uppercase">For companies</span>
        <h1 style="font:400 44px/1.1 var(--serif);color:var(--ink-2);margin-bottom:16px">Banking that keeps up with the business</h1>
        <p style="font-size:16.5px;color:var(--muted);margin-bottom:24px">
          One account for collections, payroll and suppliers, with an approval step you control
          and a statement your accountant will not complain about.</p>
        <div class="flexbar">
          <a class="btn btn-primary" href="<?php echo base_url('register.php'); ?>">Open a business account</a>
          <a class="btn" href="<?php echo base_url('contact.php'); ?>">Speak to the team</a>
        </div>
      </div>
      <div class="split-art">
        <img src="<?php echo base_url('assets/img/lending-square.jpg'); ?>"
             alt="A business meeting in progress" width="800" height="800" loading="lazy">
      </div>
    </div>
  </section>

  <section class="sec">
    <?php sec_head('What you get', 'Built around the jobs a finance team actually does each week.'); ?>
    <div class="fgrid reveal">
      <article><?php echo icon('send'); ?><h3>Supplier payments</h3>
        <p>Save every supplier once, then pay them in two clicks. Local transfers and international wires from the same screen.</p></article>
      <article><?php echo icon('users'); ?><h3>Payroll runs</h3>
        <p>Keep your staff as saved beneficiaries and run the same list each month, with a receipt for every line.</p></article>
      <article><?php echo icon('check'); ?><h3>An approval step</h3>
        <p>Payments above your threshold wait for a second pair of eyes before the money moves.</p></article>
      <article><?php echo icon('doc'); ?><h3>Statements that reconcile</h3>
        <p>Any date range, opening and closing balances, running column, reference on every line. Print or PDF.</p></article>
      <article><?php echo icon('bank'); ?><h3>Working capital</h3>
        <p>Short-term facilities priced up front, with the full repayment schedule visible before you apply.</p></article>
      <article><?php echo icon('card'); ?><h3>Company cards</h3>
        <p>Issue virtual cards per person or per project, each with its own spending ceiling, frozen in one tap.</p></article>
    </div>
  </section>

  <section class="sec">
    <div class="band reveal">
      <div style="max-width:56ch;margin-bottom:32px">
        <div class="rule"></div>
        <h2>Opening a business account</h2>
        <p>Four documents and a conversation. Most accounts are open within two working days.</p>
      </div>
      <div class="steps" style="counter-reset:s">
        <article style="border-top-color:rgba(255,255,255,.2)">
          <h3 style="color:var(--ink-2)">Register online</h3>
          <p style="color:var(--muted)">Open a standard account first — it takes about a minute.</p></article>
        <article style="border-top-color:rgba(255,255,255,.2)">
          <h3 style="color:var(--ink-2)">Send your documents</h3>
          <p style="color:var(--muted)">Certificate of incorporation, directors' ID, proof of address and a board resolution.</p></article>
        <article style="border-top-color:rgba(255,255,255,.2)">
          <h3 style="color:var(--ink-2)">Get verified</h3>
          <p style="color:var(--muted)">We confirm the details and switch the account to business, with the limits to match.</p></article>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="cta-band reveal">
      <h2>Tell us about your business</h2>
      <p>Send us a line about what you do and how money moves through it, and we will tell you
         plainly whether we are a good fit.</p>
      <a class="btn btn-primary" href="<?php echo base_url('contact.php'); ?>">Contact the business team</a>
    </div>
  </section>
</div>

<?php site_foot();
