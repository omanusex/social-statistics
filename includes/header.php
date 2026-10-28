<?php
/**
 * Public page header. Before including, a page may set:
 *   $pageTitle  - text for <title> and the heading
 *   $activeNav  - key of the highlighted menu item
 */
$pageTitle = $pageTitle ?? t('site.name');
$activeNav = $activeNav ?? '';
$nav = [
    'home'    => ['index.php',       'nav.home',    'house'],
    'explore' => ['explore.php',     'nav.explore', 'bar-chart-line'],
    'compare' => ['compare.php',     'nav.compare', 'globe2'],
    'study'   => ['local-study.php', 'nav.study',   'people'],
    'about'   => ['about.php',       'nav.about',   'info-circle'],
];
$isRtl = Lang::dir() === 'rtl';
?>
<!doctype html>
<html lang="<?= e(Lang::current()) ?>" dir="<?= e(Lang::dir()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(t('site.short')) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Source+Sans+3:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="<?= asset($isRtl ? 'vendor/bootstrap/bootstrap.rtl.min.css' : 'vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/icons/bootstrap-icons.min.css') ?>">
    <?php foreach ($pageStyles ?? [] as $style): ?>
        <link rel="stylesheet" href="<?= asset($style) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<a class="visually-hidden-focusable skip-link" href="#main"><?= e(t('nav.skip')) ?></a>

<nav class="navbar navbar-expand-lg navbar-dark site-nav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('index.php') ?>">
            <span class="brand-mark"><i class="bi bi-bar-chart-fill"></i></span>
            <span><?= e(t('site.short')) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="<?= e(t('nav.menu')) ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeNav === $key ? 'active' : '' ?>"
                           href="<?= url($href) ?>" <?= $activeNav === $key ? 'aria-current="page"' : '' ?>>
                            <i class="bi bi-<?= $icon ?>"></i> <?= e(t($label)) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= e(t('nav.language')) ?>">
                    <a href="<?= e(lang_switch_url('en')) ?>" class="btn <?= Lang::current() === 'en' ? 'btn-light' : 'btn-outline-light' ?>" lang="en">EN</a>
                    <a href="<?= e(lang_switch_url('ar')) ?>" class="btn <?= Lang::current() === 'ar' ? 'btn-light' : 'btn-outline-light' ?>" lang="ar">عربي</a>
                </div>
                <a href="<?= url('admin/index.php') ?>" class="btn btn-sm btn-outline-light" title="<?= e(t('nav.admin')) ?>">
                    <i class="bi bi-shield-lock"></i> <span class="d-lg-none d-xl-inline"><?= e(t('nav.admin')) ?></span>
                </a>
            </div>
        </div>
    </div>
</nav>

<main id="main" class="py-4">
    <div class="container">
        <?php foreach (take_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(t('common.close')) ?>"></button>
            </div>
        <?php endforeach; ?>
