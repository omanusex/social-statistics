<?php
/**
 * Browse, download and delete the stored statistics.
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::require();

$countries = StatRepository::countries();
$metrics = StatRepository::metrics();
$platforms = StatRepository::platforms();

// Build the WHERE clause from the filters
$where = ['1 = 1'];
$params = [];
$filters = ['country' => input('country'), 'metric' => input('metric'), 'platform' => input('platform'), 'device' => input('device'), 'period' => input('period')];
if ($filters['country'] !== '')  { $where[] = 'c.iso2 = ?';   $params[] = $filters['country']; }
if ($filters['metric'] !== '')   { $where[] = 'm.code = ?';   $params[] = $filters['metric']; }
if ($filters['platform'] !== '') { $where[] = 'p.slug = ?';   $params[] = $filters['platform']; }
if ($filters['device'] !== '')   { $where[] = 's.device = ?'; $params[] = $filters['device']; }
if (preg_match('/^\d{4}(-\d{2})?$/', $filters['period'])) {
    $where[] = 's.period LIKE ?';
    $params[] = $filters['period'] . '%';
}
$whereSql = implode(' AND ', $where);
$from = "FROM stats s
         JOIN countries c ON c.id = s.country_id
         JOIN metrics m ON m.id = s.metric_id
         JOIN sources so ON so.id = s.source_id
         LEFT JOIN platforms p ON p.id = s.platform_id
         WHERE $whereSql";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete_one') {
        Database::query('DELETE FROM stats WHERE id = ?', [(int) $_POST['id']]);
        AuditLog::write('delete_value', 'Deleted stats row #' . (int) $_POST['id']);
        flash('success', t('data.deleted', ['n' => 1]));
    } elseif (($_POST['action'] ?? '') === 'delete_filtered' && count($where) > 1) {
        $ids = array_column(Database::all("SELECT s.id $from", $params), 'id');
        foreach (array_chunk($ids, 500) as $chunk) {
            Database::query('DELETE FROM stats WHERE id IN (' . implode(',', array_map('intval', $chunk)) . ')');
        }
        AuditLog::write('delete_values', 'Deleted ' . count($ids) . ' rows with filter ' . json_encode(array_filter($filters)));
        flash('success', t('data.deleted', ['n' => count($ids)]));
    }
    redirect('admin/data.php?' . http_build_query(array_filter($filters)));
}

$total = (int) Database::value("SELECT COUNT(*) $from", $params);
$select = "SELECT s.id, c.iso2, c.name_en, c.name_ar, m.code AS metric, m.unit, m.granularity, p.name AS platform, s.device, s.period, s.value, so.name AS source, s.import_id, s.updated_at $from ORDER BY s.period DESC, c.iso2, p.sort_order";

// CSV download of everything that matches the filters
if (input('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stats-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['country_iso2', 'platform', 'metric', 'device', 'period', 'value', 'source'], ',', '"', '\\');
    foreach (Database::query($select, $params) as $r) {
        fputcsv($out, [$r['iso2'], $r['platform'], $r['metric'], $r['device'], substr($r['period'], 0, 7), $r['value'], $r['source']], ',', '"', '\\');
    }
    exit;
}

$perPage = 50;
$page = max(1, (int) input('page', '1'));
$pages = max(1, (int) ceil($total / $perPage));
$rows = Database::all($select . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

$pageTitle = t('admin.nav_data');
$activeAdmin = 'data';
require __DIR__ . '/../includes/admin_header.php';
?>
<form method="get" class="card filter-card mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label" for="country"><?= e(t('filter.country')) ?></label>
            <select class="form-select form-select-sm" id="country" name="country">
                <option value=""><?= e(t('common.all')) ?></option>
                <?php foreach ($countries as $c): ?><option value="<?= e($c['iso2']) ?>" <?= $filters['country'] === $c['iso2'] ? 'selected' : '' ?>><?= e(country_name($c)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="metric"><?= e(t('filter.metric')) ?></label>
            <select class="form-select form-select-sm" id="metric" name="metric">
                <option value=""><?= e(t('common.all')) ?></option>
                <?php foreach ($metrics as $m): ?><option value="<?= e($m['code']) ?>" <?= $filters['metric'] === $m['code'] ? 'selected' : '' ?>><?= e(metric_name($m)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="platform"><?= e(t('filter.platform')) ?></label>
            <select class="form-select form-select-sm" id="platform" name="platform">
                <option value=""><?= e(t('common.all')) ?></option>
                <?php foreach ($platforms as $p): ?><option value="<?= e($p['slug']) ?>" <?= $filters['platform'] === $p['slug'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="device"><?= e(t('filter.device')) ?></label>
            <select class="form-select form-select-sm" id="device" name="device">
                <option value=""><?= e(t('common.all')) ?></option>
                <?php foreach (StatRepository::DEVICES as $d): ?><option value="<?= $d ?>" <?= $filters['device'] === $d ? 'selected' : '' ?>><?= e(t('device.' . $d)) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label" for="period"><?= e(t('chart.period')) ?></label>
            <input class="form-control form-control-sm" id="period" name="period" placeholder="2025-06" value="<?= e($filters['period']) ?>">
        </div>
        <div class="col-6 col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-grow-1"><?= e(t('common.apply')) ?></button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/data.php') ?>"><?= e(t('common.reset')) ?></a>
        </div>
    </div>
</form>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="text-muted"><?= e(t('data.count', ['n' => number_format($total)])) ?></span>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/data.php', array_filter($filters) + ['export' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> <?= e(t('data.export')) ?></a>
        <?php if (count($where) > 1 && $total > 0): ?>
            <form method="post" data-confirm="<?= e(t('data.confirm_filtered', ['n' => $total])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_filtered">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> <?= e(t('data.delete_filtered')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr>
                <th><?= e(t('filter.country')) ?></th><th><?= e(t('filter.metric')) ?></th><th><?= e(t('filter.platform')) ?></th>
                <th><?= e(t('filter.device')) ?></th><th><?= e(t('chart.period')) ?></th><th class="text-end"><?= e(t('chart.value')) ?></th>
                <th><?= e(t('about.source')) ?></th><th><?= e(t('imports.import')) ?></th><th></th>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(country_name($r)) ?></td>
                        <td class="small"><code><?= e($r['metric']) ?></code></td>
                        <td><?= e($r['platform'] ?? '–') ?></td>
                        <td><?= e(t('device.' . $r['device'])) ?></td>
                        <td><?= e($r['granularity'] === 'monthly' ? substr($r['period'], 0, 7) : substr($r['period'], 0, 4)) ?></td>
                        <td class="text-end"><?= e(fmt_value($r['value'], $r['unit'])) ?></td>
                        <td class="small"><?= e($r['source']) ?></td>
                        <td class="small"><?= $r['import_id'] ? '#' . (int) $r['import_id'] : '–' ?></td>
                        <td class="text-end">
                            <form method="post" data-confirm="<?= e(t('data.confirm_one')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_one">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-sm btn-link text-danger p-0" title="<?= e(t('common.delete')) ?>" aria-label="<?= e(t('common.delete')) ?>"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="9" class="text-muted p-3"><?= e(t('common.no_data')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
    <nav class="mt-3" aria-label="<?= e(t('common.pages')) ?>">
        <ul class="pagination pagination-sm flex-wrap">
            <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('admin/data.php', array_filter($filters) + ['page' => $p])) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
