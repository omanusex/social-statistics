<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (Auth::user()) {
        Auth::logout();
    }
    flash('success', t('login.logged_out'));
}
redirect('admin/login.php');
