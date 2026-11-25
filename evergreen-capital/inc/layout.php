<?php
/** Page shells. layout_head() ... page content ... layout_foot() */

function nav_user()
{
    return array(
        array('dashboard.php',    'Overview',      'grid'),
        array('transfer.php',     'Send money',    'send'),
        array('deposit.php',      'Add funds',     'plus'),
        array('withdraw.php',     'Withdraw',      'down'),
        array('transactions.php', 'Transactions',  'list'),
        array('statement.php',    'Statements',    'doc'),
        array('loans.php',        'Loans',         'bank'),
        array('cards.php',        'Cards',         'card'),
        array('beneficiaries.php','Beneficiaries', 'users'),
        array('messages.php',     'Inbox',         'mail'),
        array('profile.php',      'Profile',       'user'),
        array('security.php',     'Security',      'lock'),
    );
}

function nav_admin()
{
    $p = pending_counts();
    return array(
        array('index.php',        'Dashboard',    'grid',  0),
        array('users.php',        'Customers',    'users', 0),
        array('requests.php',     'Approvals',    'check', $p['deposits'] + $p['withdrawals'] + $p['transfers']),
        array('loans.php',        'Loans',        'bank',  $p['loans']),
        array('cards.php',        'Cards',        'card',  $p['cards']),
        array('kyc.php',          'KYC review',   'id',    $p['kyc']),
        array('transactions.php', 'Ledger',       'list',  0),
        array('messages.php',     'Messaging',    'mail',  0),
        array('enquiries.php',    'Enquiries',    'id',    $p['enquiries']),
        array('settings.php',     'Settings',     'cog',   0),
        array('audit.php',        'Audit log',    'eye',   0),
    );
}

function icon($name)
{
    $p = array(
        'grid'  => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
        'send'  => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
        'plus'  => '<path d="M12 5v14M5 12h14"/>',
        'down'  => '<path d="M12 5v14M19 12l-7 7-7-7"/>',
        'list'  => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'doc'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'bank'  => '<path d="M3 21h18M4 10h16M5 10V21M19 10V21M9 10v11M15 10v11M12 3 3 8h18z"/>',
        'card'  => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>',
        'mail'  => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'user'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'lock'  => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'check' => '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14.01l-3-3"/>',
        'id'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 9h4M15 13h4M5.5 16c.6-1.5 2-2 3.5-2s2.9.5 3.5 2"/>',
        'cog'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.4a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.2-2.9H1a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 2.6 7a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H7a1.7 1.7 0 0 0 1-1.5V1a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V7a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'info'  => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'eye'   => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'out'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    );
    $d = isset($p[$name]) ? $p[$name] : $p['grid'];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/** Evergreen tree monogram, inline SVG (no extra request, crisp at any size). */
function brand_mark($size = 40)
{
    static $n = 0;
    $id = 'vmg' . (++$n);
    return '<svg class="vmark wm-mark" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 40 40" aria-hidden="true">'
        . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="#16A586"/><stop offset="1" stop-color="#06362E"/></linearGradient></defs>'
        . '<rect width="40" height="40" rx="12" fill="url(#' . $id . ')"/>'
        . '<rect x=".5" y=".5" width="39" height="39" rx="11.5" fill="none" stroke="#fff" stroke-opacity=".18"/>'
        . '<path d="M14.6 15.4 20 9.6l5.4 5.8" fill="none" stroke="#3EE0B0" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.2 21.6 20 13.8l7.8 7.8" fill="none" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.8 27.8 20 18l10.2 9.8Z" fill="#fff" stroke="#fff" stroke-width="2.4" stroke-linejoin="round"/><rect x="18.4" y="27.6" width="3.2" height="4.4" rx="1" fill="#D2AE62"/></svg>';
}

/** Wordmark: monogram + site name + small caps line. */
function brand_wordmark($href, $class = 'wordmark', $sub = null)
{
    if ($sub === null) $sub = t('brand.sub');
    return '<a class="' . e($class) . '" href="' . e($href) . '">' . brand_mark()
        . '<span class="wm-text">' . e(setting('site_name', 'Evergreen Capital')) . '<small>' . e($sub) . '</small></span></a>';
}

/** Light / dark theme switch (choice is remembered in the browser). */
function theme_toggle($class = '')
{
    return '<button type="button" class="themetoggle ' . e($class) . '" data-theme-toggle aria-label="Switch light / dark theme" title="Switch light / dark theme">'
        . '<svg class="ic i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>'
        . '<svg class="ic i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>'
        . '</button>';
}

/** Applies the saved theme before first paint, so there is no flash. */
function theme_boot()
{
    return "<script>try{if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>\n";
}

/** Favicon and touch-icon tags, shared by every page. */
function favicon_tags()
{
    $b = base_url('assets/img/');
    return '<link rel="icon" href="' . base_url('favicon.ico') . '" sizes="any">' . "\n"
         . '<link rel="icon" type="image/svg+xml" href="' . $b . 'favicon.svg">' . "\n"
         . '<link rel="icon" type="image/png" sizes="32x32" href="' . $b . 'favicon-32.png">' . "\n"
         . '<link rel="icon" type="image/png" sizes="16x16" href="' . $b . 'favicon-16.png">' . "\n"
         . '<link rel="apple-touch-icon" href="' . $b . 'apple-touch-icon.png">' . "\n"
         . '<meta name="theme-color" content="#06201C">' . "\n";
}

/**
 * @param string $title
 * @param string $ctx  user | admin | auth | plain
 */
function layout_head($title, $ctx = 'user')
{
    $brand = setting('site_name', 'Evergreen Capital');
    $u = auth_user();
    $file = current_file();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo e($title . ' · ' . $brand); ?></title>
<meta name="robots" content="noindex">
<?php echo favicon_tags(); ?>
<?php echo theme_boot(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:ital,opsz,wght@1,9..144,500&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo base_url('assets/css/app.css'); ?>?v=5">
</head>
<body class="ctx-<?php echo e($ctx); ?>">
<?php if ($ctx === 'auth' || $ctx === 'plain') { ?>
<?php echo theme_toggle('floating'); ?>
<main class="authwrap"><?php return; } ?>

<a class="skip" href="#main">Skip to content</a>
<input type="checkbox" id="navtoggle" class="navtoggle" hidden>

<header class="topbar">
  <label for="navtoggle" class="burger" aria-label="Menu">
    <span></span><span></span><span></span>
  </label>
  <a class="topbrand" href="<?php echo base_url($ctx === 'admin' ? 'admin/index.php' : 'user/dashboard.php'); ?>"><?php echo e($brand); ?></a>
  <div class="topright">
    <?php if ($ctx === 'user') {
        $unread = count_where('messages', "user_id = ? AND is_read = 0", array($u['id']));
        if ($unread) echo '<a class="bell" href="' . base_url('user/messages.php') . '">' . icon('mail') . '<i>' . $unread . '</i></a>';
    } ?>
    <?php echo theme_toggle(); ?>
    <span class="avatar"><?php echo e(initials($u['first_name'], $u['last_name'])); ?></span>
  </div>
</header>

<aside class="side">
  <a class="brand" href="<?php echo base_url($ctx === 'admin' ? 'admin/index.php' : 'user/dashboard.php'); ?>">
    <?php echo brand_mark(); ?>
    <span class="brand-text"><?php echo e($brand); ?><small><?php echo $ctx === 'admin' ? 'Administration' : 'Personal banking'; ?></small></span>
  </a>

  <nav class="nav">
  <?php
  if ($ctx === 'admin') {
      foreach (nav_admin() as $item) {
          list($href, $label, $ico, $badge) = $item;
          $on = $file === $href ? ' class="on"' : '';
          echo '<a href="' . base_url('admin/' . $href) . '"' . $on . '>' . icon($ico) . '<span>' . e($label) . '</span>';
          if ($badge > 0) echo '<b class="badge">' . (int)$badge . '</b>';
          echo '</a>';
      }
  } else {
      foreach (nav_user() as $item) {
          list($href, $label, $ico) = $item;
          $on = $file === $href ? ' class="on"' : '';
          echo '<a href="' . base_url('user/' . $href) . '"' . $on . '>' . icon($ico) . '<span>' . e($label) . '</span></a>';
      }
  }
  ?>
  </nav>

  <div class="sidefoot">
    <div class="whoami">
      <span class="avatar"><?php echo e(initials($u['first_name'], $u['last_name'])); ?></span>
      <div>
        <strong><?php echo e(user_name($u)); ?></strong>
        <small><?php echo $ctx === 'admin' ? 'Administrator' : 'No. ' . e($u['account_number']); ?></small>
      </div>
    </div>
    <div class="flexbar">
      <a class="signout" href="<?php echo base_url('logout.php'); ?>"><?php echo icon('out'); ?> Sign out</a>
      <?php echo theme_toggle(); ?>
    </div>
  </div>
</aside>

<label for="navtoggle" class="scrim"></label>
<main id="main" class="main">
<?php
}

function layout_foot($ctx = 'user')
{
    if ($ctx === 'auth' || $ctx === 'plain') {
        echo '</main><script src="' . base_url('assets/js/app.js') . '"></script></body></html>';
        return;
    }
    ?>
  <?php if ($ctx === 'admin' && setting('admin_notice') !== '') { ?>
    <div class="adminnote" role="note">
      <?php echo icon('info'); ?>
      <span><?php echo e(setting('admin_notice')); ?></span>
      <small>Staff only — customers and visitors never see this.</small>
    </div>
  <?php } ?>
  <footer class="pagefoot">
    <span><?php echo e(setting('site_legal', setting('site_name'))); ?><?php
      if (setting('footer_note') !== '') echo ' · ' . e(setting('footer_note')); ?></span>
    <span>Need help? <a href="mailto:<?php echo e(setting('support_email')); ?>"><?php echo e(setting('support_email')); ?></a></span>
  </footer>
</main>
<script src="<?php echo base_url('assets/js/app.js'); ?>?v=5"></script>
</body>
</html>
<?php
}

/** Page title block. */
function page_title($title, $sub = '', $actionHtml = '')
{
    echo '<div class="phead"><div><h1>' . e($title) . '</h1>';
    if ($sub) echo '<p>' . e($sub) . '</p>';
    echo '</div>';
    if ($actionHtml) echo '<div class="phead-act">' . $actionHtml . '</div>';
    echo '</div>' . render_flash();
}
