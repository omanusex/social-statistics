<?php
/**
 * Manage the local study questionnaire: open/close it, download or clear the answers.
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    switch ($_POST['action'] ?? '') {
        case 'toggle':
            $open = Settings::get('survey_open') === '1' ? '0' : '1';
            Settings::set('survey_open', $open);
            AuditLog::write('survey_toggle', $open === '1' ? 'Survey opened' : 'Survey closed');
            flash('success', t($open === '1' ? 'asurvey.opened' : 'asurvey.closed'));
            break;
        case 'demo_off':
            Settings::set('survey_is_demo', '0');
            flash('success', t('asurvey.demo_off'));
            break;
        case 'delete_all':
            $n = SurveyRepository::deleteAll();
            Settings::set('survey_is_demo', '0');
            AuditLog::write('survey_delete', "Deleted all $n responses");
            flash('success', t('asurvey.deleted', ['n' => $n]));
            break;
    }
    redirect('admin/survey.php');
}

if (input('export') === 'csv') {
    AuditLog::write('survey_export', 'Downloaded survey responses');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="survey-responses-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $rows = SurveyRepository::export();
    fputcsv($out, array_keys($rows[0] ?? array_flip(['id', 'age_group', 'gender', 'platforms_used', 'favourite', 'main_purpose', 'daily_hours', 'late_night_use', 'origin', 'created_at'])), ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, $row, ',', '"', '\\');
    }
    exit;
}

$isOpen = Settings::get('survey_open') === '1';
$isDemo = Settings::get('survey_is_demo') === '1';
$counts = Database::all('SELECT origin, COUNT(*) AS n FROM survey_responses GROUP BY origin');
$counts = array_column($counts, 'n', 'origin');

$pageTitle = t('admin.nav_survey');
$activeAdmin = 'survey';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= e(t('asurvey.status')) ?></div>
            <div class="card-body">
                <p>
                    <?= e(t('asurvey.currently')) ?>
                    <span class="badge text-bg-<?= $isOpen ? 'success' : 'secondary' ?>"><?= e(t($isOpen ? 'asurvey.open' : 'asurvey.closed_label')) ?></span>
                </p>
                <form method="post" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <button class="btn btn-<?= $isOpen ? 'outline-secondary' : 'primary' ?>">
                        <i class="bi bi-<?= $isOpen ? 'pause-circle' : 'play-circle' ?>"></i> <?= e(t($isOpen ? 'asurvey.close' : 'asurvey.open_btn')) ?>
                    </button>
                </form>
                <a href="<?= url('survey.php') ?>" target="_blank"><?= e(t('asurvey.view_form')) ?> <i class="bi bi-box-arrow-up-right"></i></a>
                <?php if ($isDemo): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        <?= e(t('asurvey.demo_notice')) ?>
                        <form method="post" class="mt-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="demo_off">
                            <button class="btn btn-sm btn-outline-dark"><?= e(t('asurvey.demo_off_btn')) ?></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= e(t('asurvey.responses')) ?></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span><?= e(t('asurvey.from_import')) ?></span><strong><?= (int) ($counts['import'] ?? 0) ?></strong></li>
                <li class="list-group-item d-flex justify-content-between"><span><?= e(t('asurvey.from_online')) ?></span><strong><?= (int) ($counts['online'] ?? 0) ?></strong></li>
            </ul>
            <div class="card-body d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="<?= url('admin/survey.php', ['export' => 'csv']) ?>"><i class="bi bi-filetype-csv"></i> <?= e(t('data.export')) ?></a>
                <a class="btn btn-outline-primary" href="<?= url('admin/import.php') ?>"><i class="bi bi-upload"></i> <?= e(t('asurvey.import')) ?></a>
                <form method="post" data-confirm="<?= e(t('asurvey.confirm_delete')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_all">
                    <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> <?= e(t('asurvey.delete_all')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
