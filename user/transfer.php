<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

$codeLabels = array(
    'cot' => array('COT code', 'Cost of transfer code issued by your account officer.'),
    'imf' => array('IMF code', 'International monetary clearance code for cross-border settlement.'),
    'tax' => array('Tax clearance code', 'Confirms the transfer is cleared for tax.'),
    'atc' => array('Anti-terrorist certificate', 'Compliance clearance for high-value payments.'),
);

/** Build the authorisation steps this bank requires. */
function auth_steps()
{
    $steps = array();
    if (setting('require_pin', '1') === '1') $steps[] = 'pin';
    if (setting('enable_codes', '0') === '1') {
        foreach (explode(',', setting('code_sequence', 'cot,imf,tax')) as $c) {
            $c = trim($c);
            if ($c !== '') $steps[] = 'code:' . $c;
        }
    }
    if (setting('require_otp', '0') === '1') $steps[] = 'otp';
    return $steps;
}

$steps = auth_steps();
$stage = 'form';
$tf = isset($_SESSION['tf']) ? $_SESSION['tf'] : null;

if (get('cancel') === '1') { unset($_SESSION['tf']); redirect('user/transfer.php'); }

/* ---------------- step 1: details ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('stage') === 'form') {
    csrf_check();
    $kind = post('kind', 'internal');
    $data = array(
        'kind'                => $kind,
        'amount'              => amount_in('amount'),
        'beneficiary_name'    => post('beneficiary_name'),
        'beneficiary_account' => post('beneficiary_account'),
        'bank_name'           => $kind === 'internal' ? setting('site_legal', setting('site_name')) : post('bank_name'),
        'bank_address'        => post('bank_address'),
        'swift'               => post('swift'),
        'routing'             => post('routing'),
        'iban'                => post('iban'),
        'country'             => post('country'),
        'narration'           => post('narration'),
    );

    $errs = array();
    if ($data['amount'] <= 0) $errs[] = 'Enter an amount above zero.';
    if ($data['beneficiary_account'] === '') $errs[] = 'Enter the destination account number.';

    if ($kind === 'internal') {
        $rcv = find_user_by_account($data['beneficiary_account']);
        if (!$rcv) $errs[] = 'No account here matches that number.';
        elseif ((int)$rcv['id'] === (int)$u['id']) $errs[] = 'You cannot send money to yourself.';
        else $data['beneficiary_name'] = user_name($rcv);
    } else {
        if ($data['beneficiary_name'] === '') $errs[] = 'Enter the beneficiary name.';
        if ($data['bank_name'] === '') $errs[] = 'Enter the receiving bank.';
        if ($kind === 'wire' && $data['swift'] === '') $errs[] = 'Wire transfers need a SWIFT/BIC code.';
    }

    $fee = transfer_fee($data['amount']);
    $available = available_balance($u['id']);
    if ($data['amount'] + $fee > $available) {
        $errs[] = 'Insufficient funds. This costs ' . money($data['amount'] + $fee)
            . ' and you have ' . money($available) . ' available.';
    }

    if ($errs) {
        flash('error', implode(' ', $errs));
    } else {
        $_SESSION['tf'] = array('data' => $data, 'i' => 0, 'started' => time());
        if (!$steps) {
            $r = create_transfer($u, $data);
            unset($_SESSION['tf']);
            if ($r['ok']) { finish_transfer($u, $data, $r); }
            flash('error', $r['msg']);
            redirect('user/transfer.php');
        }
        redirect('user/transfer.php?auth=1');
    }
}

/* ---------------- steps 2..n: authorisation ---------------- */
if (get('auth') === '1' || post('stage') === 'auth') {
    if (!$tf) { flash('error', 'That transfer expired. Start again.'); redirect('user/transfer.php'); }
    if (time() - $tf['started'] > 1800) { unset($_SESSION['tf']); flash('error', 'That transfer expired. Start again.'); redirect('user/transfer.php'); }
    $stage = 'auth';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('stage') === 'auth') {
        csrf_check();
        $current = isset($steps[$tf['i']]) ? $steps[$tf['i']] : null;
        $ok = false;

        if ($current === 'pin') {
            if (!$u['pin']) {
                flash('error', 'Set a transfer PIN under Security before sending money.');
                redirect('user/security.php');
            }
            $ok = password_verify(post('value'), $u['pin']);
            if (!$ok) flash('error', 'That PIN is not right.');
        } elseif ($current === 'otp') {
            $entered = post('value');
            if (!empty($tf['otp']) && hash_equals($tf['otp'], $entered) && time() - $tf['otp_at'] < 600) $ok = true;
            else flash('error', 'That code is wrong or has expired.');
        } elseif ($current !== null && strpos($current, 'code:') === 0) {
            $type = substr($current, 5);
            $st = db()->prepare('SELECT * FROM transfer_codes WHERE user_id = ? AND code_type = ?');
            $st->execute(array($u['id'], $type));
            $row = $st->fetch();
            if (!$row) {
                flash('error', 'No ' . strtoupper($type) . ' code is on file for your account. Contact ' . setting('support_email') . '.');
            } elseif (hash_equals($row['code'], trim(post('value')))) {
                $ok = true;
            } else {
                flash('error', 'That code does not match our records.');
            }
        }

        if ($ok) {
            $tf['i']++;
            if ($tf['i'] >= count($steps)) {
                $r = create_transfer($u, $tf['data']);
                unset($_SESSION['tf']);
                if ($r['ok']) finish_transfer($u, $tf['data'], $r);
                flash('error', $r['msg']);
                redirect('user/transfer.php');
            }
            $_SESSION['tf'] = $tf;
            redirect('user/transfer.php?auth=1');
        }
        $_SESSION['tf'] = $tf;
        redirect('user/transfer.php?auth=1');
    }

    // Send an OTP when we arrive at that step.
    $current = isset($steps[$tf['i']]) ? $steps[$tf['i']] : null;
    if ($current === 'otp' && empty($tf['otp'])) {
        $otp = make_otp();
        $tf['otp'] = $otp;
        $tf['otp_at'] = time();
        $_SESSION['tf'] = $tf;
        send_mail($u['email'], 'Authorise your transfer', mail_template('Authorise your transfer',
            '<p>Use this code to release ' . money($tf['data']['amount']) . ' to ' . e($tf['data']['beneficiary_name']) . '.</p>
             <p style="font:600 30px/1 monospace;letter-spacing:.2em;color:#12352B">' . $otp . '</p>
             <p>The code expires in 10 minutes. If you did not start this transfer, call us at once.</p>'));
    }
}

function finish_transfer($u, $data, $r)
{
    $kindWord = $data['kind'] === 'internal' ? 'Internal transfer' : ($data['kind'] === 'wire' ? 'Wire transfer' : 'Bank transfer');
    notify_user($u, $kindWord . ' ' . ($r['status'] === 'pending' ? 'received' : 'sent'),
        $kindWord . ' ' . ($r['status'] === 'pending' ? 'received' : 'sent'),
        '<p>Reference <strong>' . e($r['ref']) . '</strong></p>
         <p>' . money($data['amount']) . ' to ' . e($data['beneficiary_name']) . ' · ' . e($data['beneficiary_account']) . '</p>
         <p>' . ($r['status'] === 'pending'
            ? 'It is queued for approval. We will email you when it settles.'
            : 'The funds have left your account.') . '</p>',
        'View receipt', base_url('user/receipt.php?ref=' . $r['ref']));

    foreach (db()->query("SELECT email FROM users WHERE role='admin'")->fetchAll() as $a) {
        send_mail($a['email'], 'Transfer awaiting review — ' . money($data['amount']),
            mail_template('Transfer to review',
                '<p><strong>' . e(user_name($u)) . '</strong> sent ' . money($data['amount']) . ' to ' . e($data['beneficiary_name']) . '.</p>
                 <p>Reference ' . e($r['ref']) . ' · status ' . e($r['status']) . '</p>',
                'Open approvals', base_url('admin/requests.php?tab=transfers')));
    }
    flash('success', $r['msg'] . ' Reference ' . $r['ref'] . '.');
    redirect('user/receipt.php?ref=' . $r['ref']);
}

/* ---------------- render ---------------- */
$st = db()->prepare('SELECT * FROM beneficiaries WHERE user_id = ? ORDER BY name');
$st->execute(array($u['id']));
$bens = $st->fetchAll();

layout_head('Send money', 'user');

if ($stage === 'auth') {
    $current = isset($steps[$tf['i']]) ? $steps[$tf['i']] : 'pin';
    $d = $tf['data'];
    $fee = transfer_fee($d['amount']);
    $stepNo = $tf['i'] + 1;
    $stepTotal = count($steps);

    if ($current === 'pin') { $title = 'Transfer PIN'; $hint = 'The four-digit PIN you set under Security.'; $len = 4; }
    elseif ($current === 'otp') { $title = 'Emailed code'; $hint = 'We sent a six-digit code to ' . $u['email'] . '.'; $len = 6; }
    else {
        $type = substr($current, 5);
        $title = isset($codeLabels[$type]) ? $codeLabels[$type][0] : strtoupper($type) . ' code';
        $hint = isset($codeLabels[$type]) ? $codeLabels[$type][1] : 'Enter the code issued for this account.';
        $len = 20;
    }

    page_title('Authorise this transfer', 'Step ' . $stepNo . ' of ' . $stepTotal . '. Nothing leaves your account until every step clears.');
    ?>
    <div class="grid g2" style="align-items:start">
      <section class="card">
        <span class="eyebrow"><?php echo e($title); ?></span>
        <h2><?php echo e($title); ?></h2>
        <p class="sub"><?php echo e($hint); ?></p>
        <form method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="stage" value="auth">
          <div class="field pinbox">
            <label for="value"><?php echo e($title); ?></label>
            <input type="password" id="value" name="value" maxlength="<?php echo (int)$len; ?>" required autofocus autocomplete="one-time-code">
          </div>
          <div class="flexbar">
            <button class="btn btn-primary" type="submit">Continue</button>
            <a class="btn" href="<?php echo base_url('user/transfer.php?cancel=1'); ?>">Cancel transfer</a>
          </div>
        </form>
        <div class="divider"></div>
        <p class="small muted mb0">Never share these codes. <?php echo e(setting('site_name')); ?> staff will not ask for them by phone or email.</p>
      </section>

      <section class="card">
        <span class="eyebrow">You are sending</span>
        <div class="bal" style="margin-bottom:16px">
          <div class="bal-in">
            <span class="lbl">Amount</span>
            <span class="fig" style="font-size:34px"><?php echo money($d['amount']); ?></span>
          </div>
        </div>
        <dl class="kv">
          <dt>To</dt><dd><?php echo e($d['beneficiary_name']); ?></dd>
          <dt>Account</dt><dd><?php echo e($d['beneficiary_account']); ?></dd>
          <dt>Bank</dt><dd><?php echo e($d['bank_name']); ?></dd>
          <?php if ($d['swift']) { ?><dt>SWIFT</dt><dd><?php echo e($d['swift']); ?></dd><?php } ?>
          <dt>Fee</dt><dd><?php echo money($fee); ?></dd>
          <dt>Total</dt><dd><strong><?php echo money($d['amount'] + $fee); ?></strong></dd>
          <?php if ($d['narration']) { ?><dt>Note</dt><dd><?php echo e($d['narration']); ?></dd><?php } ?>
        </dl>
      </section>
    </div>
    <?php
    layout_foot('user');
    exit;
}

page_title('Send money', 'Move funds to another ' . setting('site_name') . ' customer or out to any bank.');
?>

<div class="grid g-main" style="align-items:start">
  <section class="card">
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="stage" value="form">

      <div class="field">
        <label for="kindSelect">Where is it going?</label>
        <select id="kindSelect" name="kind">
          <option value="internal">Another <?php echo e(setting('site_name')); ?> account — instant</option>
          <option value="domestic">Another bank in this country</option>
          <option value="wire">International wire</option>
        </select>
      </div>

      <div class="field">
        <label for="amount">Amount</label>
        <div class="money-in">
          <span><?php echo e(cur()); ?></span>
          <input type="text" id="amount" name="amount" inputmode="decimal" placeholder="0.00" required autofocus>
        </div>
        <span class="hint">Available <?php echo money(available_balance($u['id'])); ?> ·
          fee <?php echo e(setting('fee_percent', '0')); ?>%<?php if ((float)setting('fee_flat', '0') > 0) echo ' + ' . money(setting('fee_flat')); ?> ·
          limit <?php echo money(setting('max_transfer')); ?> per transfer</span>
      </div>

      <div class="field">
        <label for="acct">Destination account number</label>
        <input type="text" id="acct" name="beneficiary_account" required>
      </div>

      <div data-kind="domestic,wire" hidden>
        <div class="field"><label>Beneficiary name</label>
          <input type="text" name="beneficiary_name"></div>
        <div class="field"><label>Receiving bank</label>
          <input type="text" name="bank_name"></div>
      </div>

      <div data-kind="wire" hidden>
        <div class="row2">
          <div class="field"><label>SWIFT / BIC</label><input type="text" name="swift"></div>
          <div class="field"><label>IBAN</label><input type="text" name="iban"></div>
        </div>
        <div class="row2">
          <div class="field"><label>Routing / sort code</label><input type="text" name="routing"></div>
          <div class="field"><label>Country</label><input type="text" name="country"></div>
        </div>
        <div class="field"><label>Bank address</label><textarea name="bank_address" rows="2"></textarea></div>
      </div>

      <div class="field">
        <label for="narration">What is it for?</label>
        <input type="text" id="narration" name="narration" maxlength="120" placeholder="Rent, invoice 204, gift…">
        <span class="hint">This shows on both statements.</span>
      </div>

      <button class="btn btn-primary btn-block" type="submit" data-busy-text="Checking…">Review transfer</button>
    </form>
  </section>

  <div class="stack">
    <section class="card">
      <span class="eyebrow">How it clears</span>
      <ul class="small" style="padding-left:17px;margin:0;color:var(--muted);line-height:1.75">
        <li>Internal transfers land in the other account immediately<?php echo setting('approve_internal', '0') === '1' ? ' after approval' : ''; ?>.</li>
        <li>Outward transfers are <?php echo setting('approve_transfers', '1') === '1' ? 'held until the bank approves them' : 'released straight away'; ?>.</li>
        <li>Funds are ring-fenced the moment you submit, so your balance reflects it right away.</li>
        <li>Every transfer produces a printable receipt.</li>
      </ul>
    </section>

    <?php if ($bens) { ?>
    <section class="card">
      <span class="eyebrow">Saved beneficiaries</span>
      <p class="sub">Click one to fill the form.</p>
      <div class="stack" style="gap:8px">
        <?php foreach (array_slice($bens, 0, 6) as $b) { ?>
          <button type="button" class="btn btn-block" style="justify-content:flex-start;text-align:left"
            onclick="document.getElementById('acct').value='<?php echo e($b['account_number']); ?>';
                     var n=document.querySelector('[name=beneficiary_name]'); if(n) n.value='<?php echo e($b['name']); ?>';
                     var bk=document.querySelector('[name=bank_name]'); if(bk) bk.value='<?php echo e($b['bank_name']); ?>';">
            <span><strong><?php echo e($b['name']); ?></strong><br><small class="muted mono"><?php echo e($b['account_number']); ?></small></span>
          </button>
        <?php } ?>
      </div>
      <a class="btn btn-sm" style="margin-top:12px" href="<?php echo base_url('user/beneficiaries.php'); ?>">Manage list</a>
    </section>
    <?php } ?>
  </div>
</div>

<?php layout_foot('user');
