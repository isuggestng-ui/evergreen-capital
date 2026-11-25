<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$u = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'details') {
        db()->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, dob=?, gender=?, occupation=?,
                       address=?, city=?, state=?, country=?, zip=?, updated_at=? WHERE id=?')
            ->execute(array(post('first_name'), post('last_name'), post('phone'), post('dob'), post('gender'),
                post('occupation'), post('address'), post('city'), post('state'), post('country'), post('zip'),
                now(), $u['id']));
        audit('profile_updated', 'user', $u['id']);
        flash('success', 'Your details are saved.');
        redirect('user/profile.php');
    }

    if (post('action') === 'kyc') {
        $front = upload_file('front', 'kyc');
        $back  = upload_file('back', 'kyc');
        if (!$front) {
            flash('error', 'Upload at least the front of your ID as a JPG, PNG or PDF under 5 MB.');
        } else {
            db()->prepare('INSERT INTO kyc_documents (user_id, doc_type, id_number, front, back, status, created_at)
                           VALUES (?,?,?,?,?,?,?)')
                ->execute(array($u['id'], post('doc_type'), post('id_number'), $front, $back, 'pending', now()));
            db()->prepare('UPDATE users SET kyc_status = ? WHERE id = ?')->execute(array('pending', $u['id']));
            foreach (db()->query("SELECT email FROM users WHERE role='admin'")->fetchAll() as $a) {
                send_mail($a['email'], 'KYC documents from ' . user_name($u), mail_template('KYC to review',
                    '<p><strong>' . e(user_name($u)) . '</strong> uploaded ' . e(post('doc_type')) . ' for verification.</p>',
                    'Review documents', base_url('admin/kyc.php')));
            }
            flash('success', 'Documents received. Verification usually takes a few hours.');
        }
        redirect('user/profile.php#kyc');
    }
}

$st = db()->prepare('SELECT * FROM kyc_documents WHERE user_id = ? ORDER BY id DESC');
$st->execute(array($u['id']));
$docs = $st->fetchAll();

layout_head('Profile', 'user');
page_title('Your profile', 'Keep these current — statements and card deliveries use this address.');
?>

<div class="grid g-main" style="align-items:start">
  <section class="card">
    <h2>Personal details</h2>
    <p class="sub">Your name on statements and letters.</p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="details">
      <div class="row2">
        <div class="field"><label>First name</label><input type="text" name="first_name" value="<?php echo e($u['first_name']); ?>" required></div>
        <div class="field"><label>Last name</label><input type="text" name="last_name" value="<?php echo e($u['last_name']); ?>" required></div>
      </div>
      <div class="row2">
        <div class="field"><label>Email</label><input type="email" value="<?php echo e($u['email']); ?>" disabled>
          <span class="hint">Contact support to change the email on the account.</span></div>
        <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?php echo e($u['phone']); ?>"></div>
      </div>
      <div class="row3">
        <div class="field"><label>Date of birth</label><input type="date" name="dob" value="<?php echo e($u['dob']); ?>"></div>
        <div class="field"><label>Gender</label>
          <select name="gender">
            <option value="">Prefer not to say</option>
            <?php foreach (array('Female', 'Male', 'Other') as $g)
              echo '<option' . ($u['gender'] === $g ? ' selected' : '') . '>' . $g . '</option>'; ?>
          </select></div>
        <div class="field"><label>Occupation</label><input type="text" name="occupation" value="<?php echo e($u['occupation']); ?>"></div>
      </div>
      <div class="field"><label>Street address</label><input type="text" name="address" value="<?php echo e($u['address']); ?>"></div>
      <div class="row3">
        <div class="field"><label>City</label><input type="text" name="city" value="<?php echo e($u['city']); ?>"></div>
        <div class="field"><label>State or region</label><input type="text" name="state" value="<?php echo e($u['state']); ?>"></div>
        <div class="field"><label>Postal code</label><input type="text" name="zip" value="<?php echo e($u['zip']); ?>"></div>
      </div>
      <div class="field"><label>Country</label><input type="text" name="country" value="<?php echo e($u['country']); ?>"></div>
      <button class="btn btn-primary" type="submit">Save details</button>
    </form>

    <div class="divider" id="kyc"></div>

    <h2>Identity verification</h2>
    <p class="sub">Current status: <?php echo status_pill($u['kyc_status']); ?></p>

    <?php if ($u['kyc_status'] === 'verified') { ?>
      <div class="alert alert-success"><div>Your identity is verified. Full limits apply to your account.</div></div>
    <?php } else { ?>
    <form method="post" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="kyc">
      <div class="row2">
        <div class="field"><label>Document type</label>
          <select name="doc_type">
            <option>Passport</option><option>Driver's licence</option>
            <option>National ID card</option><option>Residence permit</option>
          </select></div>
        <div class="field"><label>Document number</label><input type="text" name="id_number"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Front of document</label><input type="file" name="front" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
        <div class="field"><label>Back <span class="muted">(if any)</span></label><input type="file" name="back" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
      </div>
      <button class="btn btn-primary" type="submit" data-busy-text="Uploading…">Submit for verification</button>
    </form>
    <?php } ?>

    <?php if ($docs) { ?>
      <div class="divider"></div>
      <table class="tbl">
        <thead><tr><th>Document</th><th>Submitted</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d) { ?>
          <tr>
            <td><?php echo e($d['doc_type']); ?><?php echo $d['note'] ? '<br><small class="muted">' . e($d['note']) . '</small>' : ''; ?></td>
            <td class="small"><?php echo nice_date($d['created_at'], false); ?></td>
            <td><?php echo status_pill($d['status']); ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    <?php } ?>
  </section>

  <div class="stack">
    <section class="card">
      <span class="eyebrow">Account</span>
      <dl class="kv">
        <dt>Account number</dt><dd><?php echo e($u['account_number']); ?></dd>
        <dt>Type</dt><dd><?php echo e($u['account_type']); ?></dd>
        <dt>Currency</dt><dd><?php echo e($u['currency']); ?></dd>
        <dt>Status</dt><dd><?php echo status_pill($u['status']); ?></dd>
        <dt>Opened</dt><dd><?php echo nice_date($u['created_at'], false); ?></dd>
        <dt>Last sign-in</dt><dd><?php echo nice_date($u['last_login']); ?></dd>
      </dl>
    </section>
    <section class="card">
      <span class="eyebrow">Why we ask</span>
      <p class="small muted mb0">Verified customers get higher transfer limits, faster loan decisions and physical card delivery.
      Documents are stored for compliance and are never shared with other customers.</p>
    </section>
  </div>
</div>

<?php layout_foot('user');
