<?php
/**
 * Compare countries: one platform (or a country-level indicator) over time in several countries,
 * a ranking of all countries for one month, and the link between internet use and platform share.
 */
require __DIR__ . '/includes/bootstrap.php';

$metrics = StatRepository::metrics(true);
$metric = StatRepository::metric(input('metric', 'traffic_share'));
if (!$metric || !in_array($metric['code'], array_column($metrics, 'code'), true)) {
    $metric = StatRepository::metric('traffic_share');
}
$monthly = $metric['granularity'] === 'monthly';
$device = $monthly && in_array(input('device'), StatRepository::DEVICES, true) ? input('device') : 'all';

$platforms = $metric['per_platform'] ? array_values(array_filter(
    StatRepository::platformsWithData((int) $metric['id']),
    fn($p) => $p['slug'] !== 'other'
)) : [];
$platform = null;
if ($platforms) {
    $platform = StatRepository::platform(input('platform', 'facebook'));
    if (!$platform || !in_array($platform['slug'], array_column($platforms, 'slug'), true)) {
        $platform = $platforms[0];
    }
}

$allCountries = StatRepository::countries();
$default = StatRepository::defaultCountry();
$chosen = array_filter(explode(',', strtoupper(input('countries'))));
if (!$chosen) {
    $chosen = array_column(array_slice(array_filter($allCountries, fn($c) => $c['region'] === 'GCC'), 0, 4), 'iso2');
}
$chosen = array_slice(array_unique($chosen), 0, 6);

[$first, $last] = StatRepository::periodRange((int) $metric['id']);
$fmt = fn(?string $d) => $d ? ($monthly ? substr($d, 0, 7) : substr($d, 0, 4)) : '';
$from = input_period('from', $last ? $fmt(max($first, date('Y-m-d', strtotime($monthly ? '-23 months' : '-14 years', strtotime($last))))) : '', $monthly);
$to = input_period('to', $fmt($last), $monthly);
$smooth = input('smooth') === '1';
$years = $last ? range((int) substr($first, 0, 4), (int) substr($last, 0, 4)) : [];

// ---- Ranking of all countries for one month (traffic share only) ----
$ranking = [];
$averages = [];
$scatter = [];
$correlation = null;
$rankMonth = null;
if ($platform && $metric['code'] === 'traffic_share') {
    $rankMonth = StatRepository::normalisePeriod(input('to'), 'monthly') ?? StatRepository::latestPeriod((int) $metric['id'], $device);
    $ranking = $rankMonth ? StatRepository::valuesByCountry((int) $metric['id'], (int) $platform['id'], $device, $rankMonth) : [];
    $defaultValue = null;
    foreach ($ranking as $r) {
        if ($r['iso2'] === $default['iso2']) {
            $defaultValue = (float) $r['value'];
        }
    }
    foreach (['GCC', 'Arab'] as $region) {
        $values = array_column(array_filter($ranking, fn($r) => $r['region'] === $region), 'value');
        if ($values) {
            $averages[$region] = array_sum($values) / count($values);
        }
    }

    // Internet users (latest year available) against the platform share of the same month
    $internetMetric = StatRepository::metric('internet_users');
    foreach ($ranking as $r) {
        if ($r['iso2'] === 'WW') {
            continue;
        }
        $c = StatRepository::country($r['iso2']);
        $net = StatRepository::latestCountryValue((int) $c['id'], 'internet_users');
        if ($net) {
            $scatter[] = ['x' => (float) $net['value'], 'y' => (float) $r['value'], 'label' => country_name($r), 'year' => substr($net['period'], 0, 4), 'highlight' => (bool) $r['is_default']];
        }
    }
    $correlation = StatRepository::correlation(array_column($scatter, 'x'), array_column($scatter, 'y'));
}

function correlation_text(?float $r): string
{
    if ($r === null) {
        return t('compare.r_none');
    }
    $strength = abs($r) >= 0.7 ? 'strong' : (abs($r) >= 0.4 ? 'moderate' : (abs($r) >= 0.2 ? 'weak' : 'none'));
    if ($strength === 'none') {
        return t('compare.r_no_link');
    }
    return t('compare.r_text', [
        'strength'  => t('compare.r_' . $strength),
        'direction' => t($r > 0 ? 'compare.r_positive' : 'compare.r_negative'),
    ]);
}

$pageTitle = t('nav.compare');
$activeNav = 'compare';
$pageScripts = ['vendor/chartjs/chart.umd.min.js', 'js/app.js', 'js/compare.js'];
$pageData = [
    'params' => [
        'metric' => $metric['code'], 'platform' => $platform['slug'] ?? '', 'countries' => implode(',', $chosen),
        'device' => $device, 'from' => $from, 'to' => $to, 'smooth' => $smooth ? '1' : '0',
    ],
    'filename' => strtolower('compare-' . $metric['code'] . '-' . ($platform['slug'] ?? 'country')),
    'scatter' => $scatter,
    'scatterAxes' => ['x' => t('compare.axis_internet'), 'y' => t('compare.axis_share', ['platform' => $platform['name'] ?? ''])],
];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= e(t('compare.title')) ?></h1>
    <p><?= e(t('compare.lead')) ?></p>
</div>

<form method="get" class="card filter-card mb-3">
    <div class="card-header"><i class="bi bi-funnel"></i> <?= e(t('explore.filters')) ?></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4 col-lg-3">
                <label class="form-label" for="metric"><?= e(t('filter.metric')) ?></label>
                <select class="form-select" id="metric" name="metric" onchange="this.form.submit()">
                    <?php foreach ($metrics as $m): ?>
                        <option value="<?= e($m['code']) ?>" <?= $m['code'] === $metric['code'] ? 'selected' : '' ?>><?= e(metric_name($m)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($platforms): ?>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="platform"><?= e(t('filter.platform')) ?></label>
                    <select class="form-select" id="platform" name="platform">
                        <?php foreach ($platforms as $p): ?>
                            <option value="<?= e($p['slug']) ?>" <?= $p['slug'] === $platform['slug'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($monthly): ?>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="device"><?= e(t('filter.device')) ?></label>
                    <select class="form-select" id="device" name="device">
                        <?php foreach (StatRepository::DEVICES as $d): ?>
                            <option value="<?= $d ?>" <?= $d === $device ? 'selected' : '' ?>><?= e(t('device.' . $d)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="from"><?= e(t('filter.from')) ?></label>
                    <input class="form-control" type="month" id="from" name="from" value="<?= e($from) ?>" min="<?= e($fmt($first)) ?>" max="<?= e($fmt($last)) ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="to"><?= e(t('filter.to')) ?></label>
                    <input class="form-control" type="month" id="to" name="to" value="<?= e($to) ?>" min="<?= e($fmt($first)) ?>" max="<?= e($fmt($last)) ?>">
                </div>
            <?php else: ?>
                <?php foreach (['from' => $from, 'to' => $to] as $name => $value): ?>
                    <div class="col-6 col-lg-2">
                        <label class="form-label" for="<?= $name ?>"><?= e(t('filter.' . $name)) ?></label>
                        <select class="form-select" id="<?= $name ?>" name="<?= $name ?>">
                            <?php foreach ($years as $y): ?>
                                <option <?= (string) $y === $value ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <fieldset class="mt-3">
            <legend class="form-label"><?= e(t('compare.countries')) ?></legend>
            <div class="row g-2">
                <?php foreach (['GCC', 'Arab', 'World'] as $region): ?>
                    <div class="col-md-4">
                        <div class="small fw-semibold text-muted mb-1"><?= e(t('region.' . $region)) ?></div>
                        <div class="platform-checks">
                            <?php foreach (array_filter($allCountries, fn($c) => $c['region'] === $region) as $c): ?>
                                <div class="form-check">
                                    <input class="form-check-input country-check" type="checkbox" id="c-<?= e($c['iso2']) ?>" value="<?= e($c['iso2']) ?>" <?= in_array($c['iso2'], $chosen, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="c-<?= e($c['iso2']) ?>"><?= e(country_name($c)) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="countries" id="countries" value="<?= e(implode(',', $chosen)) ?>">
            <div class="form-text"><?= e(t('compare.max')) ?></div>
        </fieldset>

        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
            <?php if ($monthly): ?>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="smooth" name="smooth" value="1" <?= $smooth ? 'checked' : '' ?>>
                    <label class="form-check-label" for="smooth"><?= e(t('filter.smooth')) ?></label>
                </div>
            <?php endif; ?>
            <button class="btn btn-primary ms-auto"><i class="bi bi-arrow-repeat"></i> <?= e(t('common.apply')) ?></button>
        </div>
    </div>
</form>

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <div>
            <?= e($platform ? t('compare.chart_title', ['platform' => $platform['name']]) : metric_name($metric)) ?>
            <span class="sub"><?= e(metric_name($metric)) ?><?= $monthly ? ' · ' . e(t('device.' . $device)) : '' ?></span>
        </div>
        <div class="btn-group btn-group-sm no-print">
            <button type="button" class="btn btn-outline-primary" id="btnCsv"><i class="bi bi-filetype-csv"></i> CSV</button>
            <button type="button" class="btn btn-outline-primary" id="btnPng"><i class="bi bi-image"></i> PNG</button>
            <button type="button" class="btn btn-outline-primary" id="btnLink"><i class="bi bi-link-45deg"></i> <?= e(t('explore.copy_link')) ?></button>
        </div>
    </div>
    <div class="card-body">
        <div class="chart-box tall" id="chartBox"><canvas id="mainChart" role="img" aria-label="<?= e(t('compare.title')) ?>"></canvas></div>
    </div>
</div>

<?php if ($ranking): ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <?= e(t('compare.ranking_title', ['platform' => $platform['name']])) ?>
                <span class="sub"><?= e(fmt_month($rankMonth)) ?> · <?= e(t('device.' . $device)) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr>
                        <th>#</th><th><?= e(t('chart.country')) ?></th><th><?= e(t('chart.share')) ?></th>
                        <th class="text-end"><?= e(t('compare.vs', ['country' => country_name($default)])) ?></th>
                    </tr></thead>
                    <tbody>
                        <?php $max = max(array_column($ranking, 'value')) ?: 1; ?>
                        <?php foreach ($ranking as $i => $r): ?>
                            <tr class="<?= $r['is_default'] ? 'highlight' : '' ?>">
                                <td><?= $i + 1 ?></td>
                                <td><?= e(country_name($r)) ?></td>
                                <td class="bar-cell">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" role="presentation"><div class="progress-bar" style="width: <?= round($r['value'] / $max * 100) ?>%; background: <?= e($platform['color']) ?>"></div></div>
                                        <span class="text-nowrap"><?= number_format($r['value'], 1) ?>%</span>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ($defaultValue !== null && !$r['is_default']): $diff = $r['value'] - $defaultValue; ?>
                                        <span class="<?= $diff >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($diff >= 0 ? '+' : '') . number_format($diff, 1) ?></span>
                                    <?php else: ?>–<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($averages as $region => $avg): ?>
                            <tr class="table-light">
                                <td></td>
                                <td class="fst-italic"><?= e(t('compare.average', ['region' => t('region.' . $region)])) ?></td>
                                <td><?= number_format($avg, 1) ?>%</td>
                                <td class="text-end"><?= $defaultValue !== null ? (($avg - $defaultValue >= 0 ? '+' : '') . number_format($avg - $defaultValue, 1)) : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <?= e(t('compare.scatter_title')) ?>
                <span class="sub"><?= e(t('compare.scatter_sub', ['platform' => $platform['name'], 'month' => fmt_month($rankMonth)])) ?></span>
            </div>
            <div class="card-body">
                <div class="chart-box" id="scatterBox"><canvas id="scatter" role="img" aria-label="<?= e(t('compare.scatter_title')) ?>"></canvas></div>
                <p class="mt-3 mb-0">
                    <strong><?= e(t('compare.r_label')) ?> r = <?= $correlation === null ? '–' : number_format($correlation, 2) ?></strong><br>
                    <span class="text-muted small"><?= e(correlation_text($correlation)) ?> <?= e(t('compare.r_caution')) ?></span>
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
