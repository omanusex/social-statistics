<?php
/**
 * Home page: headline numbers, platform shares, 12-month trend, map and automatic insights
 * for the selected country (Oman by default).
 */
require __DIR__ . '/includes/bootstrap.php';

$country = StatRepository::countryFromRequest();
$metric = StatRepository::metric('traffic_share');
$latest = StatRepository::latestPeriod((int) $metric['id'], 'all', (int) $country['id']);
$shares = $latest ? StatRepository::shares((int) $country['id'], (int) $metric['id'], 'all', $latest) : [];
$leader = array_values(array_filter($shares, fn($s) => $s['slug'] !== 'other'))[0] ?? null;
$internet = StatRepository::latestCountryValue((int) $country['id'], 'internet_users');
$population = StatRepository::latestCountryValue((int) $country['id'], 'population');
$respondents = SurveyRepository::count();
$insights = (new InsightEngine($country))->generate();

$pageTitle = t('nav.home');
$activeNav = 'home';
$pageStyles = ['vendor/jsvectormap/jsvectormap.min.css'];
$pageScripts = ['vendor/chartjs/chart.umd.min.js', 'vendor/jsvectormap/jsvectormap.min.js', 'vendor/jsvectormap/world.js', 'js/app.js', 'js/home.js'];
$pageData = [
    'country' => $country['iso2'],
    'shares'  => array_map(fn($s) => ['label' => $s['name'], 'value' => (float) $s['value'], 'color' => $s['color']], $shares),
    'mapTitle' => t('home.map_tooltip'),
];
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <h1><?= e(t('home.title', ['country' => country_name($country)])) ?></h1>
            <p class="lead mb-3"><?= e(t('home.lead')) ?></p>
            <a href="<?= url('explore.php', ['country' => $country['iso2']]) ?>" class="btn btn-primary"><i class="bi bi-bar-chart-line"></i> <?= e(t('home.cta_explore')) ?></a>
            <a href="<?= url('local-study.php') ?>" class="btn btn-outline-primary"><i class="bi bi-people"></i> <?= e(t('home.cta_study')) ?></a>
        </div>
        <div class="col-lg-4">
            <form method="get" class="filter-card">
                <label class="form-label" for="country"><?= e(t('home.viewing')) ?></label>
                <select class="form-select" id="country" name="country" onchange="this.form.submit()">
                    <?php require __DIR__ . '/includes/partials/country-options.php'; ?>
                </select>
                <noscript><button class="btn btn-sm btn-primary mt-2"><?= e(t('common.apply')) ?></button></noscript>
            </form>
        </div>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi">
            <div class="kpi-label"><span class="kpi-icon"><i class="bi bi-trophy"></i></span><?= e(t('home.kpi_leader')) ?></div>
            <div class="kpi-value"><?= e($leader['name'] ?? '–') ?></div>
            <div class="kpi-note"><?= $leader ? e(t('home.kpi_leader_note', ['value' => number_format($leader['value'], 1), 'month' => fmt_month($latest)])) : e(t('common.no_data')) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi">
            <div class="kpi-label"><span class="kpi-icon"><i class="bi bi-wifi"></i></span><?= e(t('home.kpi_internet')) ?></div>
            <div class="kpi-value"><?= $internet ? e(number_format($internet['value'], 1)) . '%' : '–' ?></div>
            <div class="kpi-note"><?= $internet ? e(t('home.kpi_wb_note', ['year' => substr($internet['period'], 0, 4)])) : e(t('common.no_data')) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi">
            <div class="kpi-label"><span class="kpi-icon"><i class="bi bi-people"></i></span><?= e(t('home.kpi_population')) ?></div>
            <div class="kpi-value"><?= $population ? e(fmt_value($population['value'], 'people')) : '–' ?></div>
            <div class="kpi-note"><?= $population ? e(t('home.kpi_pop_note', ['year' => substr($population['period'], 0, 4)])) : e(t('common.no_data')) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi">
            <div class="kpi-label"><span class="kpi-icon"><i class="bi bi-clipboard-data"></i></span><?= e(t('home.kpi_survey')) ?></div>
            <div class="kpi-value"><?= e(number_format($respondents)) ?></div>
            <div class="kpi-note"><a href="<?= url('local-study.php') ?>"><?= e(t('home.kpi_survey_note')) ?></a></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <?= e(t('home.shares_title')) ?>
                <span class="sub"><?= e(country_name($country)) ?> · <?= e(fmt_month($latest)) ?> · <?= e(t('device.all')) ?></span>
            </div>
            <div class="card-body">
                <div class="chart-box" id="sharesBox"><canvas id="sharesChart" aria-label="<?= e(t('home.shares_title')) ?>" role="img"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <?= e(t('home.trend_title')) ?>
                <span class="sub"><?= e(t('home.trend_sub')) ?></span>
            </div>
            <div class="card-body">
                <div class="chart-box" id="trendBox"><canvas id="trendChart" aria-label="<?= e(t('home.trend_title')) ?>" role="img"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <?= e(t('home.map_title')) ?>
                <span class="sub" id="mapSub"><?= e(t('home.map_sub')) ?></span>
            </div>
            <div class="card-body">
                <div id="map" class="map-box" role="img" aria-label="<?= e(t('home.map_title')) ?>"></div>
                <div id="mapLegend" class="small mt-2"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <?= e(t('home.insights_title')) ?>
                <span class="sub"><?= e(t('home.insights_sub')) ?></span>
            </div>
            <div class="card-body">
                <?php if ($insights): ?>
                    <ul class="insight-list">
                        <?php foreach ($insights as $insight): ?>
                            <li><i class="bi bi-<?= e($insight['icon']) ?>"></i><span><?= e($insight['text']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0"><?= e(t('common.no_data')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> <?= e(t('home.note')) ?> <a href="<?= url('about.php') ?>#methodology"><?= e(t('home.note_link')) ?></a></p>

<?php require __DIR__ . '/includes/footer.php'; ?>
