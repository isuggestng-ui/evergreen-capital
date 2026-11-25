<?php
/** Left-hand panel on the sign-in / register screens. */
function auth_side($heading = null, $points = null)
{
    $brand = setting('site_name', 'Evergreen Capital');
    if ($heading === null) $heading = t('auth.side_title');
    if ($points === null) {
        $points = array(t('auth.side_p1'), t('auth.side_p2'), t('auth.side_p3'));
    }
    ?>
    <div class="authside">
      <?php echo brand_wordmark(base_url('index.php')); ?>
      <div>
        <h2><?php echo e($heading); ?></h2>
        <ul>
          <?php foreach ($points as $p) echo '<li>' . icon('check') . '<span>' . e($p) . '</span></li>'; ?>
        </ul>
        <div class="v-cardx" aria-hidden="true">
          <div class="row"><span class="nm"><?php echo brand_mark(22); ?> Evergreen</span><span class="tier">Platinum</span></div>
          <div class="row"><span class="chip"></span></div>
          <span class="num">4716 •••• •••• 2048</span>
          <div class="row"><span><small>Member since</small><b><?php echo date('Y'); ?></b></span><span class="circles"><i></i><i></i></span></div>
        </div>
      </div>
      <p class="authnote">
        <?php
        $bits = array_filter(array(setting('site_legal'), setting('address')));
        echo e(implode(' · ', $bits));
        if (setting('footer_note') !== '') echo ($bits ? '<br>' : '') . e(setting('footer_note'));
        ?>
      </p>
    </div>
    <?php
}

function auth_logo()
{
    echo brand_wordmark(base_url('index.php'), 'wordmark authlogo');
}
