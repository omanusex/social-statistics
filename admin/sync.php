<?php
/**
 * Download the latest data from StatCounter and the World Bank API.
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$countries = StatRepository::countries();
$lastComplete = date('Y-m', strtotime('first day of last month'));
$result = null;
$resultSource = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    set_time_limit(600);
    $importer = new DataImporter(Auth::id());

    try {
        if (($_POST['source'] ?? '') === 'statcounter') {
            $isoList = (array) ($_POST['countries'] ?? []);
            $chosen = array_values(array_filter($countries, fn($c) => in_array($c['iso2'], $isoList, true)));
            $devices = array_values(array_intersect(StatRepository::DEVICES, (array) ($_POST['devices'] ?? [])));
            $from = preg_match('/^\d{4}-\d{2}$/', $_POST['from'] ?? '') ? $_POST['from'] : '2023-01';
            $to = preg_match('/^\d{4}-\d{2}$/', $_POST['to'] ?? '') ? min($_POST['to'], $lastComplete) : $lastComplete;
            if (!$chosen || !$devices) {
                flash('danger', t('sync.err_choose'));
                redirect('admin/sync.php');
            }
            $result = $importer->syncStatCounter($chosen, $devices, min($from, $to), $to);
            $resultSource = 'StatCounter';
        } elseif (($_POST['source'] ?? '') === 'worldbank') {
            $fromYear = max(1990, min((int) ($_POST['from_year'] ?? 2010), (int) date('Y')));
            $result = $importer->syncWorldBank($fromYear, (int) date('Y'));
            $resultSource = 'World Bank';
        }
    } catch (Throwable $ex) {
        flash('danger', t('sync.failed') . ' ' . $ex->getMessage());
        redirect('admin/sync.php');
    }
}

$pageTitle = t('admin.nav_sync');
$activeAdmin = 'sync';
require __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($result): ?>
    <div class="alert alert-<?= $result['status'] === 'success' ? 'success' : 'warning' ?>">
        <strong><?= e($resultSource) ?>:</strong>
        <?= e(t('sync.result', ['inserted' => $result['inserted'], 'updated' => $result['updated'], 'failed' => $result['failed']])) ?>
        <?php if ($result['messages']): ?>
            <pre class="import-log mt-2 mb-0"><?= e(implode("\n", $result['messages'])) ?></pre>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" class="card h-100" data-busy="<?= e(t('sync.busy')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="source" value="statcounter">
            <div class="card-header">
                <i class="bi bi-bar-chart"></i> StatCounter Global Stats
                <span class="sub"><?= e(t('sync.sc_help')) ?></span>
            </div>
            <div class="card-body">
                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold"><?= e(t('compare.countries')) ?>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="document.querySelectorAll('.sync-country').forEach(c => c.checked = true)"><?= e(t('common.select_all')) ?></button>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="document.querySelectorAll('.sync-country').forEach(c => c.checked = false)"><?= e(t('common.select_none')) ?></button>
                    </legend>
                    <div class="platform-checks">
                        <?php foreach ($countries as $c): ?>
                            <div class="form-check">
                                <input class="form-check-input sync-country" type="checkbox" name="countries[]" value="<?= e($c['iso2']) ?>" id="sc-<?= e($c['iso2']) ?>" checked>
                                <label class="form-check-label" for="sc-<?= e($c['iso2']) ?>"><?= e(country_name($c)) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold"><?= e(t('filter.device')) ?></legend>
                    <div class="platform-checks">
                        <?php foreach (StatRepository::DEVICES as $d): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="devices[]" value="<?= $d ?>" id="sd-<?= $d ?>" checked>
                                <label class="form-check-label" for="sd-<?= $d ?>"><?= e(t('device.' . $d)) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="from"><?= e(t('filter.from')) ?></label>
                        <input class="form-control" type="month" id="from" name="from" value="2023-01" min="2009-01" max="<?= e($lastComplete) ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="to"><?= e(t('filter.to')) ?></label>
                        <input class="form-control" type="month" id="to" name="to" value="<?= e($lastComplete) ?>" min="2009-01" max="<?= e($lastComplete) ?>">
                    </div>
                </div>
                <p class="small text-muted"><?= e(t('sync.sc_note')) ?></p>
                <button class="btn btn-primary"><i class="bi bi-cloud-download"></i> <?= e(t('sync.run')) ?></button>
            </div>
        </form>
    </div>
    <div class="col-xl-5">
        <form method="post" class="card h-100" data-busy="<?= e(t('sync.busy')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="source" value="worldbank">
            <div class="card-header">
                <i class="bi bi-bank"></i> World Bank API
                <span class="sub"><?= e(t('sync.wb_help')) ?></span>
            </div>
            <div class="card-body">
                <ul class="small">
                    <?php foreach (WorldBankClient::INDICATORS as $code => $metricCode): ?>
                        <li><code><?= e($code) ?></code> – <?= e(metric_name(StatRepository::metric($metricCode))) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="from_year"><?= e(t('sync.from_year')) ?></label>
                    <input class="form-control" type="number" id="from_year" name="from_year" value="2010" min="1990" max="<?= date('Y') ?>" style="max-width: 160px">
                </div>
                <button class="btn btn-primary"><i class="bi bi-cloud-download"></i> <?= e(t('sync.run')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
