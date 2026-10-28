<?php
/**
 * Admin dashboard: database totals, data freshness, latest imports and activity.
 */
require __DIR__ . '/../includes/bootstrap.php';
$user = Auth::require();

$totals = StatRepository::totals();
$sources = StatRepository::sources();
$imports = DataImporter::recent(6);
$activity = AuditLog::latest(8);
$surveyCount = SurveyRepository::count();
$latestMonth = StatRepository::latestPeriod((int) StatRepository::metric('traffic_share')['id']);
$staleDays = $latestMonth ? (int) ((time() - strtotime($latestMonth . ' +1 month')) / 86400) : null;

$pageTitle = t('admin.nav_dashboard');
$activeAdmin = 'dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<p class="text-muted"><?= e(t('admin.welcome', ['name' => $user['name']])) ?></p>

<?php if ($totals['rows_total'] == 0): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-3">
        <i class="bi bi-cloud-download fs-4"></i>
        <div class="flex-grow-1"><?= e(t('admin.empty_db')) ?></div>
        <a class="btn btn-primary" href="<?= url('admin/sync.php') ?>"><?= e(t('admin.nav_sync')) ?></a>
    </div>
<?php elseif ($staleDays !== null && $staleDays > 45): ?>
    <div class="alert alert-warning"><?= e(t('admin.stale', ['month' => fmt_month($latestMonth)])) ?>
        <a href="<?= url('admin/sync.php') ?>"><?= e(t('admin.nav_sync')) ?></a></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['database', t('admin.stat_rows'), number_format($totals['rows_total'])],
        ['geo-alt', t('about.n_countries'), (int) $totals['countries']],
        ['calendar3', t('admin.stat_latest'), fmt_month($latestMonth)],
        ['clipboard-data', t('about.n_survey'), number_format($surveyCount)],
    ] as [$icon, $label, $value]): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi">
                <div class="kpi-label"><span class="kpi-icon"><i class="bi bi-<?= $icon ?>"></i></span><?= e($label) ?></div>
                <div class="kpi-value"><?= e($value) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header"><?= e(t('about.sources_title')) ?></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($sources as $s): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($s['name']) ?></span>
                        <span class="text-muted small"><?= $s['last_synced_at'] ? e(t('admin.synced_at', ['date' => $s['last_synced_at']])) : e(t('admin.never')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between"><?= e(t('admin.nav_imports')) ?> <a class="small" href="<?= url('admin/imports.php') ?>"><?= e(t('common.view_all')) ?></a></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th><?= e(t('about.source')) ?></th><th><?= e(t('imports.rows')) ?></th><th><?= e(t('imports.status')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($imports as $i): ?>
                            <tr>
                                <td><?= (int) $i['id'] ?></td>
                                <td><?= e($i['source_name']) ?></td>
                                <td>+<?= (int) $i['rows_inserted'] ?> / ~<?= (int) $i['rows_updated'] ?></td>
                                <td><?php require __DIR__ . '/../includes/partials/import-status.php'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$imports): ?><tr><td colspan="4" class="text-muted"><?= e(t('common.no_data')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header"><?= e(t('admin.activity')) ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($activity as $a): ?>
                            <tr>
                                <td class="text-nowrap small text-muted"><?= e($a['created_at']) ?></td>
                                <td><span class="badge text-bg-light"><?= e($a['action']) ?></span></td>
                                <td class="small"><?= e($a['user_name'] ?? '–') ?></td>
                                <td class="small"><?= e($a['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
