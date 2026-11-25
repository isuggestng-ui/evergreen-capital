<?php
require __DIR__ . '/inc/bootstrap.php';
if (auth_user()) audit('logout', 'user', auth_user()['id']);
session_unset();
session_destroy();
session_start();
session_regenerate_id(true);
session_start();
$_SESSION['flash']['success'] = 'You are signed out.';
header('Location: ' . base_url('login.php'));
exit;
