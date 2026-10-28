<?php
/**
 * About the project, data sources, definitions of each statistic, methodology and privacy.
 */
require __DIR__ . '/includes/bootstrap.php';

$sources = StatRepository::sources();
$metrics = StatRepository::metrics();
$totals = StatRepository::totals();

$pageTitle = t('nav.about');
$activeNav = 'about';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= e(t('about.title')) ?></h1>
    <p><?= e(t('about.lead')) ?></p>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5 text-brand"><?= e(t('about.aim_title')) ?></h2>
                <p><?= e(t('about.aim')) ?></p>
                <h2 class="h5 text-brand mt-4"><?= e(t('about.users_title')) ?></h2>
                <p class="mb-0"><?= e(t('about.users')) ?></p>
            </div>
        </div>

        <div class="card mb-3" id="sources">
            <div class="card-header"><?= e(t('about.sources_title')) ?></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th><?= e(t('about.source')) ?></th><th><?= e(t('about.license')) ?></th><th><?= e(t('about.updated')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($sources as $s): ?>
                            <tr>
                                <td>
                                    <?php if ($s['url']): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><?= e($s['name']) ?></a><?php else: ?><?= e($s['name']) ?><?php endif; ?>
                                    <div class="small text-muted"><?= e(t('about.source_' . $s['code'])) ?></div>
                                </td>
                                <td class="small"><?= e($s['license']) ?></td>
                                <td class="small text-nowrap"><?= $s['last_synced_at'] ? e(date('Y-m-d', strtotime($s['last_synced_at']))) : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?= e(t('about.metrics_title')) ?></div>
            <div class="card-body">
                <dl class="mb-0">
                    <?php foreach ($metrics as $m): ?>
                        <dt><?= e(metric_name($m)) ?> <span class="badge text-bg-light"><?= e($m['unit']) ?> · <?= e(t('about.' . $m['granularity'])) ?></span></dt>
                        <dd class="text-muted"><?= e(metric_description($m)) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <div class="card mb-3" id="methodology">
            <div class="card-header"><?= e(t('about.method_title')) ?></div>
            <div class="card-body">
                <ul class="mb-0">
                    <?php foreach (['m1', 'm2', 'm3', 'm4', 'm5'] as $k): ?>
                        <li class="mb-2"><?= e(t('about.' . $k)) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= e(t('about.limits_title')) ?></div>
            <div class="card-body">
                <ul class="mb-0">
                    <?php foreach (['l1', 'l2', 'l3', 'l4'] as $k): ?>
                        <li class="mb-2"><?= e(t('about.' . $k)) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><?= e(t('about.numbers')) ?></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span><?= e(t('about.n_values')) ?></span><strong><?= number_format($totals['rows_total']) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(t('about.n_countries')) ?></span><strong><?= (int) $totals['countries'] ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(t('about.n_platforms')) ?></span><strong><?= (int) $totals['platforms'] ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(t('about.n_survey')) ?></span><strong><?= number_format(SurveyRepository::count()) ?></strong></li>
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-shield-check"></i> <?= e(t('about.privacy_title')) ?></div>
            <div class="card-body small"><?= e(t('about.privacy')) ?></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
