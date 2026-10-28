<?php
/**
 * Upload an Excel / CSV file in three steps:
 *   1. upload  - the file is checked and stored temporarily
 *   2. preview - the parsed rows and any errors are shown
 *   3. confirm - the rows are saved to the database
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$countries = StatRepository::countries();
$importer = new DataImporter(Auth::id());
$pending = $_SESSION['pending_import'] ?? null;
$preview = null;

/** Parse the stored file according to its type. */
function parse_pending(DataImporter $importer, array $p): array
{
    return match ($p['type']) {
        DataImporter::TYPE_STATCOUNTER => $importer->parseStatCounterFile($p['path'], (int) $p['country_id'], $p['device']),
        DataImporter::TYPE_TEMPLATE    => $importer->parseTemplate($p['path'], $p['ext']),
        DataImporter::TYPE_SURVEY      => $importer->parseSurvey($p['path'], $p['ext']),
    };
}

function forget_pending(): void
{
    if (!empty($_SESSION['pending_import']['path']) && is_file($_SESSION['pending_import']['path'])) {
        unlink($_SESSION['pending_import']['path']);
    }
    unset($_SESSION['pending_import']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel') {
        forget_pending();
        redirect('admin/import.php');
    }

    if ($action === 'upload') {
        forget_pending();
        $file = $_FILES['file'] ?? null;
        $type = $_POST['type'] ?? '';
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        $error = match (true) {
            !$file || $file['error'] === UPLOAD_ERR_NO_FILE => 'import.err_nofile',
            $file['error'] !== UPLOAD_ERR_OK                => 'import.err_upload',
            $file['size'] > $config['upload']['max_bytes']  => 'import.err_size',
            !in_array($ext, $config['upload']['extensions'], true) => 'import.err_type',
            !in_array($type, [DataImporter::TYPE_STATCOUNTER, DataImporter::TYPE_TEMPLATE, DataImporter::TYPE_SURVEY], true) => 'import.err_kind',
            $type === DataImporter::TYPE_STATCOUNTER && $ext !== 'csv' => 'import.err_sc_csv',
            default => null,
        };
        // An .xlsx file must really be a ZIP archive
        if (!$error && $ext === 'xlsx' && file_get_contents($file['tmp_name'], false, null, 0, 2) !== 'PK') {
            $error = 'import.err_type';
        }

        $country = null;
        if (!$error && $type === DataImporter::TYPE_STATCOUNTER) {
            $country = StatRepository::country($_POST['country'] ?? '');
            if (!$country || !in_array($_POST['device'] ?? '', StatRepository::DEVICES, true)) {
                $error = 'import.err_sc_options';
            }
        }

        if ($error) {
            flash('danger', t($error, ['mb' => $config['upload']['max_bytes'] / 1048576]));
            redirect('admin/import.php');
        }

        // Store under a random name outside the public folders
        $path = STORAGE_PATH . '/uploads/' . bin2hex(random_bytes(16)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $path);
        $_SESSION['pending_import'] = [
            'type'       => $type,
            'path'       => $path,
            'ext'        => $ext,
            'filename'   => basename($file['name']),
            'country_id' => $country['id'] ?? null,
            'country'    => $country ? country_name($country) : null,
            'device'     => $_POST['device'] ?? 'all',
            'replace'    => !empty($_POST['replace']),
        ];
        redirect('admin/import.php');
    }

    if ($action === 'confirm' && $pending) {
        try {
            $parsed = parse_pending($importer, $pending);
            if ($pending['type'] === DataImporter::TYPE_SURVEY) {
                $summary = $importer->saveSurvey($parsed['records'], $pending['filename'], count($parsed['errors']), $pending['replace']);
                Settings::set('survey_is_demo', '0');
            } else {
                $source = $pending['type'] === DataImporter::TYPE_STATCOUNTER ? 'statcounter' : 'manual';
                $summary = $importer->saveStats($parsed['records'], $source, 'file', $pending['filename'], count($parsed['errors']), $parsed['errors']);
            }
            flash('success', t('import.saved', ['id' => $summary['importId'], 'inserted' => $summary['inserted'], 'updated' => $summary['updated'], 'failed' => $summary['failed']]));
        } catch (Throwable $ex) {
            flash('danger', t('import.err_parse') . ' ' . $ex->getMessage());
        }
        forget_pending();
        redirect('admin/imports.php');
    }
}

if ($pending) {
    try {
        $preview = parse_pending($importer, $pending);
    } catch (Throwable $ex) {
        flash('danger', t('import.err_parse') . ' ' . $ex->getMessage());
        forget_pending();
        redirect('admin/import.php');
    }
}

// Lookup tables for showing names in the preview
$platformNames = array_column(StatRepository::platforms(), 'name', 'id');
$countryNames = [];
foreach ($countries as $c) {
    $countryNames[$c['id']] = country_name($c);
}
$metricNames = [];
foreach (StatRepository::metrics() as $m) {
    $metricNames[$m['id']] = metric_name($m);
}

$pageTitle = t('admin.nav_import');
$activeAdmin = 'import';
require __DIR__ . '/../includes/admin_header.php';
?>

<?php if (!$preview): ?>
<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" enctype="multipart/form-data" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload">
            <div class="card-header"><?= e(t('import.step1')) ?></div>
            <div class="card-body">
                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold"><?= e(t('import.kind')) ?></legend>
                    <?php foreach ([DataImporter::TYPE_STATCOUNTER, DataImporter::TYPE_TEMPLATE, DataImporter::TYPE_SURVEY] as $i => $type): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input import-type" type="radio" name="type" id="type-<?= $type ?>" value="<?= $type ?>" <?= $i === 0 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="type-<?= $type ?>">
                                <strong><?= e(t('import.type_' . $type)) ?></strong>
                                <span class="d-block small text-muted"><?= e(t('import.type_' . $type . '_help')) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <div class="row g-3 mb-3 type-options" data-type="statcounter">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="country"><?= e(t('filter.country')) ?></label>
                        <select class="form-select" id="country" name="country">
                            <?php $country = StatRepository::defaultCountry(); require __DIR__ . '/../includes/partials/country-options.php'; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="device"><?= e(t('filter.device')) ?></label>
                        <select class="form-select" id="device" name="device">
                            <?php foreach (StatRepository::DEVICES as $d): ?>
                                <option value="<?= $d ?>"><?= e(t('device.' . $d)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-check mb-3 type-options" data-type="survey" hidden>
                    <input class="form-check-input" type="checkbox" id="replace" name="replace" value="1" checked>
                    <label class="form-check-label" for="replace"><?= e(t('import.replace')) ?></label>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="file"><?= e(t('import.file')) ?></label>
                    <input class="form-control" type="file" id="file" name="file" accept=".csv,.xlsx" required>
                    <div class="form-text"><?= e(t('import.file_help', ['mb' => $config['upload']['max_bytes'] / 1048576])) ?></div>
                </div>
                <button class="btn btn-primary"><i class="bi bi-eye"></i> <?= e(t('import.preview_btn')) ?></button>
            </div>
        </form>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-download"></i> <?= e(t('import.templates')) ?></div>
            <div class="card-body small">
                <p><?= e(t('import.templates_help')) ?></p>
                <ul>
                    <li><a href="<?= url('admin/template.php', ['name' => 'stats']) ?>">stats_template.csv</a> – <?= e(implode(', ', DataImporter::TEMPLATE_COLUMNS)) ?></li>
                    <li><a href="<?= url('admin/template.php', ['name' => 'survey']) ?>">survey_template.csv</a> – <?= e(implode(', ', DataImporter::SURVEY_COLUMNS)) ?></li>
                </ul>
                <p class="mb-1 fw-semibold"><?= e(t('import.codes')) ?></p>
                <p class="mb-1"><?= e(t('import.codes_metric')) ?> <code><?= e(implode(', ', array_column(StatRepository::metrics(), 'code'))) ?></code></p>
                <p class="mb-1"><?= e(t('import.codes_platform')) ?> <code><?= e(implode(', ', array_column(StatRepository::platforms(), 'slug'))) ?></code></p>
                <p class="mb-0"><?= e(t('import.codes_sc')) ?> <a href="https://gs.statcounter.com/social-media-stats" target="_blank" rel="noopener">gs.statcounter.com</a></p>
            </div>
        </div>
    </div>
</div>
<script>
    // Show only the options that belong to the chosen file type
    document.querySelectorAll('.import-type').forEach((radio) => radio.addEventListener('change', () => {
        document.querySelectorAll('.type-options').forEach((el) => { el.hidden = el.dataset.type !== radio.value; });
    }));
</script>

<?php else: ?>
<div class="card">
    <div class="card-header">
        <?= e(t('import.step2')) ?>
        <span class="sub"><?= e($pending['filename']) ?> · <?= e(t('import.type_' . $pending['type'])) ?><?= $pending['country'] ? ' · ' . e($pending['country']) . ' · ' . e(t('device.' . $pending['device'])) : '' ?></span>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 mb-3">
            <span class="badge text-bg-success fs-6"><?= e(t('import.valid_rows', ['n' => count($preview['records'])])) ?></span>
            <span class="badge text-bg-<?= $preview['errors'] ? 'danger' : 'secondary' ?> fs-6"><?= e(t('import.error_rows', ['n' => count($preview['errors'])])) ?></span>
        </div>

        <?php if ($preview['errors']): ?>
            <pre class="import-log alert alert-danger"><?= e(implode("\n", array_slice($preview['errors'], 0, 50))) ?></pre>
        <?php endif; ?>

        <?php if ($preview['records']): ?>
            <p class="small text-muted"><?= e(t('import.first_rows')) ?></p>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-striped">
                    <?php if ($pending['type'] === DataImporter::TYPE_SURVEY): ?>
                        <thead><tr><?php foreach (DataImporter::SURVEY_COLUMNS as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($preview['records'], 0, 10) as $r): ?>
                                <tr><td><?= e($r['age_group']) ?></td><td><?= e($r['gender']) ?></td><td><?= e(implode(', ', $r['platforms'])) ?></td>
                                    <td><?= e($r['favourite']) ?></td><td><?= e($r['main_purpose']) ?></td><td><?= e($r['daily_hours']) ?></td><td><?= e($r['late_night_use']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php else: ?>
                        <thead><tr><th><?= e(t('filter.country')) ?></th><th><?= e(t('filter.platform')) ?></th><th><?= e(t('filter.metric')) ?></th><th><?= e(t('filter.device')) ?></th><th><?= e(t('chart.period')) ?></th><th><?= e(t('chart.value')) ?></th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($preview['records'], 0, 10) as [$c, $p, $m, $d, $period, $value]): ?>
                                <tr><td><?= e($countryNames[$c] ?? $c) ?></td><td><?= e($p ? ($platformNames[$p] ?? $p) : '–') ?></td><td><?= e($metricNames[$m] ?? $m) ?></td>
                                    <td><?= e(t('device.' . $d)) ?></td><td><?= e(substr($period, 0, 7)) ?></td><td><?= e($value) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
            <?php if ($pending['type'] === DataImporter::TYPE_SURVEY && $pending['replace']): ?>
                <div class="alert alert-warning small"><?= e(t('import.replace_warning', ['n' => SurveyRepository::count()])) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="d-flex gap-2">
            <?php if ($preview['records']): ?>
                <form method="post" data-busy="<?= e(t('sync.busy')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="confirm">
                    <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> <?= e(t('import.confirm', ['n' => count($preview['records'])])) ?></button>
                </form>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <button class="btn btn-outline-secondary"><?= e(t('common.cancel')) ?></button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
