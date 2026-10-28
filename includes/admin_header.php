<?php
/**
 * Admin layout header (sidebar menu). Pages set $pageTitle and $activeAdmin,
 * and must call Auth::require() before including this file.
 */
$user = Auth::user();
$menu = [
    'dashboard' => ['admin/index.php',   'admin.nav_dashboard', 'speedometer2', false],
    'sync'      => ['admin/sync.php',    'admin.nav_sync',      'cloud-download', false],
    'import'    => ['admin/import.php',  'admin.nav_import',    'upload', false],
    'imports'   => ['admin/imports.php', 'admin.nav_imports',   'clock-history', false],
    'data'      => ['admin/data.php',    'admin.nav_data',      'table', false],
    'survey'    => ['admin/survey.php',  'admin.nav_survey',    'clipboard-data', false],
    'users'     => ['admin/users.php',   'admin.nav_users',     'people', false],
    'log'       => ['admin/log.php',     'admin.nav_log',       'journal-text', true],
    'backup'    => ['admin/backup.php',  'admin.nav_backup',    'hdd', true],
];
$isRtl = Lang::dir() === 'rtl';
?>
<!doctype html>
<html lang="<?= e(Lang::current()) ?>" dir="<?= e(Lang::dir()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($pageTitle ?? t('admin.title')) ?> · <?= e(t('admin.title')) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Source+Sans+3:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset($isRtl ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="admin-body <?= $isRtl ? 'rtl' : 'ltr' ?>">
<nav class="navbar navbar-dark site-nav">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('admin/index.php') ?>">
            <span class="brand-mark"><i class="bi bi-shield-lock-fill"></i></span>
            <span><?= e(t('admin.title')) ?></span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-light" href="<?= url('index.php') ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> <span class="d-none d-sm-inline"><?= e(t('admin.view_site')) ?></span></a>
            <a href="<?= e(lang_switch_url(Lang::current() === 'ar' ? 'en' : 'ar')) ?>" class="btn btn-sm btn-outline-light"><?= Lang::current() === 'ar' ? 'EN' : 'عربي' ?></a>
            <span class="text-white-50 small d-none d-md-inline"><i class="bi bi-person-circle"></i> <?= e($user['name']) ?> (<?= e(t('role.' . $user['role'])) ?>)</span>
            <form method="post" action="<?= url('admin/logout.php') ?>" class="m-0">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-light"><i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline"><?= e(t('admin.logout')) ?></span></button>
            </form>
        </div>
    </div>
</nav>

<div class="container-fluid px-lg-4 py-4">
    <div class="row g-4">
        <aside class="col-lg-3 col-xxl-2 admin-sidebar">
            <div class="list-group">
                <?php foreach ($menu as $key => [$href, $label, $icon, $adminOnly]):
                    if ($adminOnly && $user['role'] !== Auth::ROLE_ADMIN) continue; ?>
                    <a href="<?= url($href) ?>" class="list-group-item list-group-item-action <?= ($activeAdmin ?? '') === $key ? 'active' : '' ?>">
                        <i class="bi bi-<?= $icon ?> me-2"></i><?= e(t($label)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <main class="col-lg-9 col-xxl-10" id="main">
            <h1 class="h3 mb-4 text-brand"><?= e($pageTitle ?? '') ?></h1>
            <?php foreach (take_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= nl2br(e($flash['message'])) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(t('common.close')) ?>"></button>
                </div>
            <?php endforeach; ?>
