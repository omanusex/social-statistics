<?php
/**
 * Explore: filter statistics by country, statistic type, device, period and platform,
 * then view them as a chart and a table, or download them.
 */
require __DIR__ . '/includes/bootstrap.php';

$country = StatRepository::countryFromRequest();
$metrics = StatRepository::metrics(true);
$metric = StatRepository::metric(input('metric', 'traffic_share'));
if (!$metric || !in_array($metric['code'], array_column($metrics, 'code'), true)) {
    $metric = $metrics[0] ?? StatRepository::metric('traffic_share');
}
$monthly = $metric['granularity'] === 'monthly';
$device = $monthly && in_array(input('device'), StatRepository::DEVICES, true) ? input('device') : 'all';

[$first, $last] = StatRepository::periodRange((int) $metric['id']);
$fmt = fn(?string $d) => $d ? ($monthly ? substr($d, 0, 7) : substr($d, 0, 4)) : '';
$defaultFrom = $last ? max($first, date('Y-m-d', strtotime($monthly ? '-23 months' : '-14 years', strtotime($last)))) : null;
$from = input_period('from', $fmt($defaultFrom), $monthly);
$to = input_period('to', $fmt($last), $monthly);

$platforms = $metric['per_platform'] ? StatRepository::platformsWithData((int) $metric['id']) : [];
$selected = array_filter(explode(',', input('platforms')));
if (!$selected && $platforms) {
    // Default selection: the five platforms with the highest latest value
    $latest = StatRepository::latestPeriod((int) $metric['id'], $device, (int) $country['id']);
    $ranked = $latest ? StatRepository::shares((int) $country['id'], (int) $metric['id'], $device, $latest) : [];
    $selected = array_slice(array_column(array_filter($ranked, fn($r) => $r['slug'] !== 'other'), 'slug'), 0, 5);
}
$chartType = input('chart') === 'bar' ? 'bar' : 'line';
$smooth = input('smooth') === '1';
$years = $last ? range((int) substr($first, 0, 4), (int) substr($last, 0, 4)) : [];

$pageTitle = t('nav.explore');
$activeNav = 'explore';
$pageScripts = ['vendor/chartjs/chart.umd.min.js', 'js/app.js', 'js/explore.js'];
$pageData = [
    'params' => [
        'country' => $country['iso2'], 'metric' => $metric['code'], 'device' => $device,
        'from' => $from, 'to' => $to, 'platforms' => implode(',', $selected), 'smooth' => $smooth ? '1' : '0',
    ],
    'chart' => $chartType,
    'filename' => strtolower("{$country['iso2']}-{$metric['code']}-$device"),
];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= e(t('explore.title')) ?></h1>
    <p><?= e(t('explore.lead')) ?></p>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <form method="get" class="card filter-card" id="filters">
            <div class="card-header"><i class="bi bi-funnel"></i> <?= e(t('explore.filters')) ?></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="country"><?= e(t('filter.country')) ?></label>
                    <select class="form-select" id="country" name="country">
                        <?php require __DIR__ . '/includes/partials/country-options.php'; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="metric"><?= e(t('filter.metric')) ?></label>
                    <select class="form-select" id="metric" name="metric" onchange="this.form.submit()">
                        <?php foreach ($metrics as $m): ?>
                            <option value="<?= e($m['code']) ?>" <?= $m['code'] === $metric['code'] ? 'selected' : '' ?>><?= e(metric_name($m)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($monthly): ?>
                    <div class="mb-3">
                        <label class="form-label" for="device"><?= e(t('filter.device')) ?></label>
                        <select class="form-select" id="device" name="device">
                            <?php foreach (StatRepository::DEVICES as $d): ?>
                                <option value="<?= $d ?>" <?= $d === $device ? 'selected' : '' ?>><?= e(t('device.' . $d)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-lg-12 col-xl-6">
                            <label class="form-label" for="from"><?= e(t('filter.from')) ?></label>
                            <input class="form-control" type="month" id="from" name="from" value="<?= e($from) ?>" min="<?= e($fmt($first)) ?>" max="<?= e($fmt($last)) ?>">
                        </div>
                        <div class="col-6 col-lg-12 col-xl-6">
                            <label class="form-label" for="to"><?= e(t('filter.to')) ?></label>
                            <input class="form-control" type="month" id="to" name="to" value="<?= e($to) ?>" min="<?= e($fmt($first)) ?>" max="<?= e($fmt($last)) ?>">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-2 mb-3">
                        <?php foreach (['from' => $from, 'to' => $to] as $name => $value): ?>
                            <div class="col-6">
                                <label class="form-label" for="<?= $name ?>"><?= e(t('filter.' . $name)) ?></label>
                                <select class="form-select" id="<?= $name ?>" name="<?= $name ?>">
                                    <?php foreach ($years as $y): ?>
                                        <option <?= (string) $y === $value ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($platforms): ?>
                    <fieldset class="mb-3">
                        <legend class="form-label"><?= e(t('filter.platforms')) ?></legend>
                        <div class="platform-checks">
                            <?php foreach ($platforms as $p): ?>
                                <div class="form-check">
                                    <input class="form-check-input platform-check" type="checkbox" id="p-<?= e($p['slug']) ?>" value="<?= e($p['slug']) ?>" <?= in_array($p['slug'], $selected, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="p-<?= e($p['slug']) ?>">
                                        <span class="legend-swatch" style="background: <?= e($p['color']) ?>"></span><?= e($p['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="platforms" id="platforms" value="<?= e(implode(',', $selected)) ?>">
                    </fieldset>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="chart"><?= e(t('filter.chart')) ?></label>
                    <select class="form-select" id="chart" name="chart">
                        <option value="line" <?= $chartType === 'line' ? 'selected' : '' ?>><?= e(t('filter.chart_line')) ?></option>
                        <option value="bar" <?= $chartType === 'bar' ? 'selected' : '' ?>><?= e(t('filter.chart_bar')) ?></option>
                    </select>
                </div>
                <?php if ($monthly): ?>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="smooth" name="smooth" value="1" <?= $smooth ? 'checked' : '' ?>>
                        <label class="form-check-label" for="smooth"><?= e(t('filter.smooth')) ?></label>
                        <div class="form-text"><?= e(t('filter.smooth_help')) ?></div>
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary w-100"><i class="bi bi-arrow-repeat"></i> <?= e(t('common.apply')) ?></button>
            </div>
        </form>
    </div>

    <div class="col-lg-9">
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <?= e(metric_name($metric)) ?> · <?= e(country_name($country)) ?>
                    <span class="sub"><?= e($monthly ? t('device.' . $device) . ' · ' : '') ?><?= e(metric_description($metric)) ?></span>
                </div>
                <div class="btn-group btn-group-sm no-print" role="group" aria-label="<?= e(t('explore.actions')) ?>">
                    <button type="button" class="btn btn-outline-primary" id="btnCsv"><i class="bi bi-filetype-csv"></i> CSV</button>
                    <button type="button" class="btn btn-outline-primary" id="btnPng"><i class="bi bi-image"></i> PNG</button>
                    <button type="button" class="btn btn-outline-primary" id="btnLink"><i class="bi bi-link-45deg"></i> <?= e(t('explore.copy_link')) ?></button>
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i></button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-box tall" id="chartBox"><canvas id="mainChart" role="img" aria-label="<?= e(metric_name($metric)) ?>"></canvas></div>
            </div>
            <div class="card-footer bg-white small text-muted">
                <span class="badge badge-source"><?= e(t('common.source')) ?></span> <span id="sourceName"></span>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><?= e(t('explore.table')) ?></div>
            <div class="table-responsive" style="max-height: 420px">
                <table class="table table-sm table-striped table-hover mb-0" id="dataTable">
                    <thead class="sticky-top"></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
