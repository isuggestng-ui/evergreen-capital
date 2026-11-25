<?php
/** Shared chrome for the public website (Evergreen theme, EN/RU). */

function site_head($title, $description = '', $current = '')
{
    $brand = setting('site_name', 'Evergreen Capital');
    $desc  = $description !== '' ? $description
        : $brand . '. ' . t('brand.tagline');
    $og = base_url('assets/img/og-image' . (current_lang() === 'en' ? '-en' : '') . '.jpg');
    ?><!doctype html>
<html lang="<?php echo e(current_lang()); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo e($title . ' · ' . $brand); ?></title>
<meta name="description" content="<?php echo e($desc); ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo e($brand); ?>">
<meta property="og:title" content="<?php echo e($title . ' · ' . $brand); ?>">
<meta property="og:description" content="<?php echo e($desc); ?>">
<meta property="og:image" content="<?php echo e($og); ?>">
<meta property="og:locale" content="<?php echo current_lang() === 'ru' ? 'ru_RU' : 'en_US'; ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?php echo e($og); ?>">
<?php echo favicon_tags(); ?>
<?php echo theme_boot(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:ital,opsz,wght@1,9..144,500&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo base_url('assets/css/app.css'); ?>?v=5">
<script type="application/ld+json">
<?php echo json_encode(array(
    '@context' => 'https://schema.org',
    '@type'    => 'FinancialService',
    'name'     => setting('site_legal', $brand),
    'url'      => base_url(),
    'logo'     => base_url('assets/img/icon-512.png'),
    'email'    => setting('support_email'),
    'telephone'=> setting('support_phone'),
    'address'  => array('@type' => 'PostalAddress', 'streetAddress' => setting('address')),
), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>
</head>
<body class="ctx-site">
<a class="skip" href="#main"><?php echo te('common.back_home'); ?></a>

<header class="sitebar">
  <div class="site sitebar-in">
    <input type="checkbox" id="sitenav" class="sitenav-toggle" hidden>
    <?php echo brand_wordmark(base_url('index.php')); ?>
    <?php echo theme_toggle('mob-theme'); ?>
    <label for="sitenav" class="sitenav-burger" aria-label="Menu"><span></span><span></span><span></span></label>
    <nav class="sitelinks">
      <?php
      $links = array(
          'index.php'    => t('nav.personal'),
          'business.php' => t('nav.business'),
          'help.php'     => t('nav.help'),
          'about.php'    => t('nav.about'),
          'careers.php'  => t('nav.careers'),
          'contact.php'  => t('nav.contact'),
      );
      foreach ($links as $href => $label) {
          $on = $current === $href ? ' class="on"' : '';
          echo '<a href="' . base_url($href) . '"' . $on . '>' . e($label) . '</a>';
      }
      ?>
      <a class="only-mobile" href="<?php echo base_url('login.php'); ?>"><?php echo te('nav.signin'); ?></a>
      <a class="only-mobile strong" href="<?php echo base_url('register.php'); ?>"><?php echo te('nav.open'); ?></a>
    </nav>
    <div class="acts">
      <?php echo theme_toggle(); ?>
      <?php echo lang_switcher(); ?>
      <a class="btn btn-sm" href="<?php echo base_url('login.php'); ?>"><?php echo te('nav.signin'); ?></a>
      <a class="btn btn-sm btn-primary" href="<?php echo base_url('register.php'); ?>"><?php echo te('nav.open'); ?></a>
    </div>
  </div>
</header>

<main id="main">
<?php
}

function site_foot()
{
    $brand = setting('site_name', 'Evergreen Capital');
    ?>
</main>

<footer class="sitefoot">
  <div class="site">
    <div class="cols">
      <div>
        <?php echo brand_wordmark(base_url('index.php'), 'wordmark wordmark-foot'); ?>
        <?php
        $lines = array_filter(array(setting('site_legal'), setting('address')));
        if ($lines) echo '<p style="font-size:14.2px;max-width:34ch;color:#83A89C;margin-top:14px">'
            . implode('<br>', array_map('e', $lines)) . '</p>';
        $contact = array_filter(array(setting('support_phone'), setting('support_email')));
        if ($contact) echo '<p style="font-size:14.2px;color:#83A89C">'
            . implode('<br>', array_map('e', $contact)) . '</p>';
        ?>
      </div>
      <div>
        <h4><?php echo te('foot.banking'); ?></h4>
        <a href="<?php echo base_url('index.php#accounts'); ?>"><?php echo te('foot.accounts'); ?></a>
        <a href="<?php echo base_url('index.php#transfers'); ?>"><?php echo te('foot.transfers'); ?></a>
        <a href="<?php echo base_url('index.php#lending'); ?>"><?php echo te('foot.loans'); ?></a>
        <a href="<?php echo base_url('index.php#cards'); ?>"><?php echo te('foot.cards'); ?></a>
        <a href="<?php echo base_url('business.php'); ?>"><?php echo te('foot.business'); ?></a>
      </div>
      <div>
        <h4><?php echo te('foot.company'); ?></h4>
        <a href="<?php echo base_url('about.php'); ?>"><?php echo te('foot.about'); ?></a>
        <a href="<?php echo base_url('careers.php'); ?>"><?php echo te('foot.careers'); ?></a>
        <a href="<?php echo base_url('contact.php'); ?>"><?php echo te('foot.contact'); ?></a>
        <a href="<?php echo base_url('help.php'); ?>"><?php echo te('foot.help'); ?></a>
      </div>
      <div>
        <h4><?php echo te('foot.legal'); ?></h4>
        <a href="<?php echo base_url('legal.php?doc=terms'); ?>"><?php echo te('foot.terms'); ?></a>
        <a href="<?php echo base_url('legal.php?doc=privacy'); ?>"><?php echo te('foot.privacy'); ?></a>
        <a href="<?php echo base_url('legal.php?doc=security'); ?>"><?php echo te('foot.security'); ?></a>
        <a href="<?php echo base_url('legal.php?doc=complaints'); ?>"><?php echo te('foot.complaints'); ?></a>
        <a href="<?php echo base_url('admin/login.php'); ?>"><?php echo te('foot.staff'); ?></a>
      </div>
    </div>
    <div class="legal">
      <span>&copy; <?php echo date('Y'); ?> <?php echo e(setting('site_legal') !== '' ? setting('site_legal') : $brand); ?>. <?php echo te('foot.rights'); ?></span>
      <?php if (setting('show_demo_badge', '1') === '1') { ?>
        <span class="demo-quiet"><?php echo te('brand.demo_full'); ?></span>
      <?php } ?>
      <?php if (setting('footer_note') !== '') { ?>
        <span><?php echo e(setting('footer_note')); ?></span>
      <?php } ?>
    </div>
  </div>
</footer>

<script src="<?php echo base_url('assets/js/app.js'); ?>?v=5"></script>
</body>
</html>
<?php
}

/** Section heading used across the marketing pages. */
function sec_head($title, $sub = '', $center = false)
{
    echo '<div class="sec-head' . ($center ? ' center' : '') . '"><div class="rule"></div><h2>' . e($title) . '</h2>';
    if ($sub) echo '<p>' . e($sub) . '</p>';
    echo '</div>';
}
