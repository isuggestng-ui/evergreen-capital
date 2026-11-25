<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/site.php';

$brand   = setting('site_name', 'Evergreen Capital');
$legal   = setting('site_legal', $brand);
$support = setting('support_email');

$docs = array('terms' => 'Terms of service', 'privacy' => 'Privacy notice',
              'security' => 'Security', 'complaints' => 'Complaints');
$doc = get('doc', 'terms');
if (!isset($docs[$doc])) $doc = 'terms';

site_head($docs[$doc], $docs[$doc] . ' for ' . $legal . '.', 'legal.php');
?>

<div class="site">
  <section class="sec">
    <div class="split" style="grid-template-columns:.34fr .66fr;gap:44px;align-items:start">
      <div style="position:sticky;top:88px">
        <div class="card">
          <span class="eyebrow">Legal</span>
          <?php foreach ($docs as $k => $label) { ?>
            <a href="<?php echo base_url('legal.php?doc=' . $k); ?>"
               style="display:block;padding:10px 0;border-bottom:1px solid var(--line-2);font-size:14.6px;<?php
               echo $doc === $k ? 'font-weight:600;color:var(--ink-2)' : 'color:var(--muted)'; ?>">
              <?php echo e($label); ?></a>
          <?php } ?>
        </div>
        <p class="small muted" style="margin-top:14px">Last updated <?php echo date('F Y'); ?>.</p>
      </div>

      <div class="prose reveal">
        <div class="rule"></div>
        <h1 style="font:400 38px/1.14 var(--serif);color:var(--ink-2);margin-bottom:8px"><?php echo e($docs[$doc]); ?></h1>
        <p class="muted"><?php echo e($legal); ?><?php echo setting('address') ? ' · ' . e(setting('address')) : ''; ?></p>

        <?php if ($doc === 'terms') { ?>
          <h2>1. Who we are</h2>
          <p>These terms govern your use of accounts and services provided by <?php echo e($legal); ?>
             (“we”, “us”). By opening an account you accept them. If you do not, please do not use
             the service.</p>

          <h2>2. Opening an account</h2>
          <p>You must be at least 18 and give accurate information. We may ask for identity documents
             before raising your limits, and we may decline or close an account where we are required
             to, or where information given to us proves to be false.</p>

          <h2>3. Your responsibilities</h2>
          <ul>
            <li>Keep your password, transfer PIN and one-time codes to yourself. We will never ask for them.</li>
            <li>Tell us promptly if you think someone else has used your account.</li>
            <li>Check your statements and raise anything that looks wrong within 30 days.</li>
            <li>Do not use the account for anything unlawful.</li>
          </ul>

          <h2>4. Payments</h2>
          <p>You are responsible for the details you enter. A payment sent to the wrong account
             because of details you supplied may not be recoverable. Outward payments may be held
             for review; where a payment is declined, any funds held for it are released back to
             your available balance.</p>

          <h2>5. Fees</h2>
          <p>Applicable fees are shown before you confirm a payment and again on the receipt. We
             will give notice before changing our fees.</p>

          <h2>6. Loans</h2>
          <p>Loans are offered at our discretion. The amount, rate, term and total repayable are
             shown to you before you apply and confirmed on approval. Repayments are taken from your
             available balance. You may repay early at no extra cost.</p>

          <h2>7. Suspension and closure</h2>
          <p>We may suspend an account or hold transfers where we suspect fraud, where we are
             required to by law, or where these terms have been breached. We will tell you the
             reason unless we are prohibited from doing so.</p>

          <h2>8. Liability</h2>
          <p>We are not liable for losses caused by circumstances outside our reasonable control,
             or by your failure to keep your credentials secure. Nothing here limits liability that
             cannot lawfully be limited.</p>

          <h2>9. Changes</h2>
          <p>We may update these terms. Material changes will be notified to you by email and in
             your account inbox before they take effect.</p>

          <h2>10. Contact</h2>
          <p>Questions about these terms go to <?php echo e($support); ?>.</p>

        <?php } elseif ($doc === 'privacy') { ?>
          <h2>What we collect</h2>
          <ul>
            <li><strong>Identity and contact</strong> — name, date of birth, address, email, phone, and identity documents you upload.</li>
            <li><strong>Financial</strong> — your balance, transactions, transfers, loans and cards.</li>
            <li><strong>Technical</strong> — IP address, sign-in times and device information, recorded for security.</li>
          </ul>

          <h2>Why we use it</h2>
          <ul>
            <li>To operate your account and process the payments you ask us to make.</li>
            <li>To meet legal obligations, including identity verification and financial crime monitoring.</li>
            <li>To detect and prevent fraud.</li>
            <li>To contact you about your account. We do not sell your data to anyone.</li>
          </ul>

          <h2>How long we keep it</h2>
          <p>We keep account records for as long as you are a customer, and afterwards for the period
             required by law. Sign-in and audit records are kept for security and dispute resolution.</p>

          <h2>Who we share it with</h2>
          <p>Only where necessary: payment partners to execute a transfer you asked for, regulators
             and law enforcement where legally required, and service providers bound by confidentiality.</p>

          <h2>Your rights</h2>
          <p>You may ask for a copy of your data, ask us to correct it, or ask us to delete it where
             we are not required to keep it. Write to <?php echo e($support); ?> and we will respond
             within 30 days.</p>

          <h2>Cookies</h2>
          <p>We use one essential cookie to keep you signed in. It carries no advertising or tracking
             data, and it is removed when you sign out.</p>

        <?php } elseif ($doc === 'security') { ?>
          <h2>How we protect your account</h2>
          <ul>
            <li>Passwords and PINs are stored hashed. Nobody at <?php echo e($brand); ?>, including staff, can read them.</li>
            <li>Repeated failed sign-in attempts are rate limited.</li>
            <li>Sessions expire automatically after 30 minutes of inactivity.</li>
            <li>Payments require a transfer PIN, and optionally a one-time code and clearance codes.</li>
            <li>Funds for a pending payment are held so the same money cannot be committed twice.</li>
            <li>Every staff action against an account is written to an audit log.</li>
          </ul>

          <h2>What we will never do</h2>
          <p>We will never ask for your password, transfer PIN or a one-time code — not by phone,
             email, SMS or any messaging app. Anybody who does is not us.</p>

          <h2>What you can do</h2>
          <ul>
            <li>Turn on two-step sign-in under Security.</li>
            <li>Use a password you do not use on any other site.</li>
            <li>Review your sign-in history occasionally.</li>
            <li>Freeze a card the moment something looks wrong — you can unfreeze it just as fast.</li>
          </ul>

          <h2>Reporting something</h2>
          <p>If you think your account has been compromised, or you have received a message pretending
             to be from us, contact <?php echo e($support); ?> or call <?php echo e(setting('support_phone')); ?>
             straight away.</p>

        <?php } else { ?>
          <h2>If something goes wrong</h2>
          <p>We would rather hear about it than not. Most problems are resolved on the first contact.</p>

          <h2>Step one — tell us</h2>
          <p>Write to <?php echo e($support); ?> or use our <a href="<?php echo base_url('contact.php'); ?>">contact
             form</a>. Include your account number and the reference of any transaction involved. Please
             do not include your password or PIN.</p>

          <h2>Step two — what happens next</h2>
          <ul>
            <li>We acknowledge every complaint within one working day.</li>
            <li>We aim to resolve it within five working days.</li>
            <li>If it will take longer, we tell you why and give you a date.</li>
            <li>You get our final answer in writing, with the reasoning set out.</li>
          </ul>

          <h2>Step three — if you are still unhappy</h2>
          <p>Ask for the matter to be escalated and a senior member of staff who was not involved
             will review it. If you remain dissatisfied after our final response, you may refer the
             matter to the relevant financial services regulator in your jurisdiction.</p>

          <h2>What helps us help you</h2>
          <p>Dates, amounts and transaction references. Every entry in your account carries a
             reference on its receipt, which you can print from the transaction list.</p>
        <?php } ?>

        <?php if (setting('legal_notice') !== '') { ?>
          <div class="divider"></div>
          <p class="small muted"><?php echo e(setting('legal_notice')); ?></p>
        <?php } ?>
      </div>
    </div>
  </section>
</div>

<?php site_foot();
