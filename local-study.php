<?php
/**
 * Local study: results of the questionnaire answered by young people in Oman,
 * filterable by age group and gender.
 */
require __DIR__ . '/includes/bootstrap.php';

$filters = ['age' => input('age'), 'gender' => input('gender')];
$summary = SurveyRepository::summary($filters);
$isOpen = Settings::get('survey_open') === '1';
$topFavourite = $summary['favourite'][0] ?? null;

$label = fn(string $group, array $counts) => array_map(
    fn($key, $n) => ['label' => t("survey.$group.$key"), 'value' => $n, 'color' => '#2e86ab'],
    array_keys($counts),
    $counts
);

$pageTitle = t('nav.study');
$activeNav = 'study';
$pageScripts = ['vendor/chartjs/chart.umd.min.js', 'js/app.js', 'js/study.js'];
$pageData = [
    'usage'     => array_map(fn($r) => ['label' => $r['name'], 'value' => $r['pct'], 'color' => $r['color']], $summary['usage']),
    'favourite' => array_map(fn($r) => ['label' => $r['name'], 'value' => (int) $r['n'], 'color' => $r['color']], $summary['favourite']),
    'purpose'   => $label('purpose', $summary['purpose']),
    'hours'     => $label('hours', $summary['hours']),
    'age'       => $label('age', $summary['age']),
];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
        <h1><?= e(t('study.title')) ?></h1>
        <p><?= e(t('study.lead')) ?></p>
    </div>
    <?php if ($isOpen): ?>
        <a href="<?= url('survey.php') ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> <?= e(t('study.take')) ?></a>
    <?php endif; ?>
</div>

<form method="get" class="card filter-card mb-3">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label class="form-label" for="age"><?= e(t('survey.q_age')) ?></label>
            <select class="form-select" id="age" name="age">
                <option value=""><?= e(t('common.all')) ?></option>
                <?php foreach (SurveyRepository::AGE_GROUPS as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filters['age'] === $a ? 'selected' : '' ?>><?= e(t('survey.age.' . $a)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="gender"><?= e(t('survey.q_gender')) ?></label>
            <select class="form-select" id="gender" name="gender">
                <option value=""><?= e(t('common.all')) ?></option>
                <?php foreach (SurveyRepository::GENDERS as $g): ?>
                    <option value="<?= e($g) ?>" <?= $filters['gender'] === $g ? 'selected' : '' ?>><?= e(t('survey.gender.' . $g)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> <?= e(t('common.apply')) ?></button>
        <?php if ($filters['age'] || $filters['gender']): ?>
            <a class="btn btn-link" href="<?= url('local-study.php') ?>"><?= e(t('common.reset')) ?></a>
        <?php endif; ?>
    </div>
</form>

<?php if ($summary['total'] === 0): ?>
    <div class="alert alert-info"><?= e(t('study.empty')) ?></div>
<?php else: ?>
<div class="row g-3 mb-3">
    <?php
    $tiles = [
        ['people', t('study.kpi_total'), number_format($summary['total']), t('study.kpi_total_note')],
        ['star', t('study.kpi_favourite'), $topFavourite['name'] ?? '–', $topFavourite ? t('study.kpi_favourite_note', ['n' => $topFavourite['n']]) : ''],
        ['moon-stars', t('study.kpi_late'), $summary['late_night'] . '%', t('study.kpi_late_note')],
        ['hourglass-split', t('study.kpi_heavy'), $summary['heavy_use'] . '%', t('study.kpi_heavy_note')],
    ];
    foreach ($tiles as [$icon, $title, $value, $note]): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi">
                <div class="kpi-label"><span class="kpi-icon"><i class="bi bi-<?= $icon ?>"></i></span><?= e($title) ?></div>
                <div class="kpi-value"><?= e($value) ?></div>
                <div class="kpi-note"><?= e($note) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><?= e(t('study.usage_title')) ?><span class="sub"><?= e(t('study.usage_sub')) ?></span></div>
            <div class="card-body"><div class="chart-box"><canvas id="usageChart" role="img" aria-label="<?= e(t('study.usage_title')) ?>"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><?= e(t('study.favourite_title')) ?><span class="sub"><?= e(t('study.favourite_sub')) ?></span></div>
            <div class="card-body"><div class="chart-box"><canvas id="favouriteChart" role="img" aria-label="<?= e(t('study.favourite_title')) ?>"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><?= e(t('study.purpose_title')) ?></div>
            <div class="card-body"><div class="chart-box short"><canvas id="purposeChart" role="img" aria-label="<?= e(t('study.purpose_title')) ?>"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><?= e(t('study.hours_title')) ?></div>
            <div class="card-body"><div class="chart-box short"><canvas id="hoursChart" role="img" aria-label="<?= e(t('study.hours_title')) ?>"></canvas></div></div>
        </div>
    </div>
    <div class="col-md-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><?= e(t('study.age_title')) ?></div>
            <div class="card-body"><div class="chart-box short"><canvas id="ageChart" role="img" aria-label="<?= e(t('study.age_title')) ?>"></canvas></div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-body small text-muted">
        <i class="bi bi-info-circle"></i> <?= e(t('study.why')) ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
