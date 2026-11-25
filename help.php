<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$brand = setting('site_name', 'Evergreen Capital');
$cur   = cur();

$faq = array(
  'Getting started' => array(
    array('How long does it take to open an account?',
      'About a minute. You give us your name, email and a password, and you get an account number straight away. Identity documents are only needed later, when you want higher limits or a physical card.'),
    array('Is there a minimum balance or a monthly fee?',
      'No to both. You can leave the account at zero for as long as you like without being charged for it.'),
    array('How do I get money in?',
      'Transfer from another bank using the details on your Add funds screen, pay in at an agent, or have your salary routed to us. Log the deposit in the app and we match it against our records.'),
  ),
  'Payments and transfers' => array(
    array('How long does a transfer take?',
      'Transfers to another ' . $brand . ' account land immediately. Transfers to other banks and international wires are reviewed before release, which usually takes minutes during working hours and up to one working day otherwise.'),
    array('Why is my balance lower than I expected?',
      'When you submit a payment, those funds are held straight away so the same money cannot be spent twice. Your available balance drops immediately while the ledger balance still shows the full amount. If the payment is declined, the hold is released at once.'),
    array('What are the fees?',
      'The fee is shown on the transfer screen before you confirm, and again on the receipt. There are no separate charges added later.'),
    array('What are the authorisation codes?',
      'Depending on how your account is set up, a payment may need a transfer PIN, a one-time code emailed to you, and clearance codes issued by your account officer. You are walked through each one in turn. We will never ask you for any of them by phone or email.'),
  ),
  'Loans' => array(
    array('How much can I borrow?',
      'Between ' . money(setting('loan_min', '500')) . ' and ' . money(setting('loan_max', '100000')) . ', over terms from three months. The calculator on the loans page shows the exact repayment before you apply.'),
    array('Does applying affect my balance?',
      'No. Applying costs nothing and moves nothing. Funds only appear in your account once a credit officer approves the loan.'),
    array('Can I repay early?',
      'Yes, at any time and with no penalty. Repayments come straight out of your available balance.'),
  ),
  'Cards' => array(
    array('Virtual or physical?',
      'A virtual card works online as soon as it is approved. A physical card is posted to the address on your profile, so make sure that is current first.'),
    array('I have lost my card. What now?',
      'Freeze it from the Cards screen immediately — that stops new spending at once — then contact us and we will replace it.'),
  ),
  'Security' => array(
    array('What should I do if I get a suspicious message?',
      'Do not reply and do not click anything. We will never ask for your password, PIN or one-time codes. Forward it to ' . setting('support_email') . ' and we will confirm whether it came from us.'),
    array('How do I make my account more secure?',
      'Turn on two-step sign-in under Security, set a transfer PIN you do not use anywhere else, and check the sign-in history on that page from time to time.'),
  ),
);

site_head('Help centre', 'Answers to the questions we get asked most.', 'help.php');
?>

<div class="site">
  <section class="sec">
    <div class="sec-head center reveal">
      <div class="rule"></div>
      <h1 style="font:400 42px/1.12 var(--serif);color:var(--ink-2);margin-bottom:12px">How can we help?</h1>
      <p>The questions we are asked most, answered plainly. If yours is not here,
         <a href="<?php echo base_url('contact.php'); ?>">write to us</a>.</p>
    </div>
  </section>

  <section class="sec" style="padding-top:0">
    <div class="split" style="gap:44px;align-items:start">
      <div style="position:sticky;top:88px">
        <div class="card">
          <span class="eyebrow">Jump to</span>
          <?php foreach (array_keys($faq) as $group) { ?>
            <a href="#<?php echo e(strtolower(str_replace(' ', '-', $group))); ?>"
               style="display:block;padding:9px 0;border-bottom:1px solid var(--line-2);font-size:14.6px">
              <?php echo e($group); ?></a>
          <?php } ?>
        </div>
        <div class="card" style="margin-top:16px">
          <span class="eyebrow">Still stuck?</span>
          <p class="small muted" style="margin-bottom:12px">A person will reply within one working day.</p>
          <a class="btn btn-sm btn-primary" href="<?php echo base_url('contact.php'); ?>">Contact us</a>
        </div>
      </div>

      <div class="faq">
        <?php foreach ($faq as $group => $items) { ?>
          <h2 id="<?php echo e(strtolower(str_replace(' ', '-', $group))); ?>"
              style="font:400 27px/1.2 var(--serif);color:var(--ink-2);margin:30px 0 8px"><?php echo e($group); ?></h2>
          <?php foreach ($items as $qa) { ?>
            <details>
              <summary><?php echo e($qa[0]); ?></summary>
              <p><?php echo e($qa[1]); ?></p>
            </details>
          <?php } ?>
        <?php } ?>
      </div>
    </div>
  </section>
</div>

<?php site_foot();
