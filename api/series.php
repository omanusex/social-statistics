<?php
/**
 * GET api/series.php
 * Time series for one country.
 *   country   ISO2 code (default: Oman)
 *   metric    metric code (default: traffic_share)
 *   device    all | mobile | desktop
 *   from, to  "YYYY-MM" (monthly) or "YYYY" (yearly); default = last 24 months / 15 years
 *   platforms comma separated platform slugs (optional)
 *   top       only the N platforms with the highest latest value (optional)
 *   smooth    1 = 3-month moving average
 */
require __DIR__ . '/../includes/bootstrap.php';

$country = StatRepository::countryFromRequest();
$metric = StatRepository::metric(input('metric', 'traffic_share'));
if (!$metric) {
    json_response(['error' => t('error.bad_metric')], 400);
}
$device = $metric['granularity'] === 'yearly' ? 'all' : input('device', 'all');
if (!in_array($device, StatRepository::DEVICES, true)) {
    json_response(['error' => t('error.bad_device')], 400);
}

[$first, $last] = StatRepository::periodRange((int) $metric['id']);
$payload = [
    'metric'   => ['code' => $metric['code'], 'name' => metric_name($metric), 'unit' => $metric['unit'], 'granularity' => $metric['granularity']],
    'country'  => ['iso2' => $country['iso2'], 'name' => country_name($country)],
    'labels'   => [],
    'periods'  => [],
    'datasets' => [],
    'source'   => $metric['code'] === 'traffic_share' ? 'StatCounter Global Stats' : ($metric['per_platform'] ? 'Manual upload' : 'World Bank Open Data'),
];
if (!$last) {
    json_response($payload);
}

$granularity = $metric['granularity'];
$defaultFrom = date('Y-m-d', strtotime($granularity === 'yearly' ? '-14 years' : '-23 months', strtotime($last)));
$to = StatRepository::normalisePeriod(input('to'), $granularity) ?? $last;
$from = StatRepository::normalisePeriod(input('from'), $granularity) ?? max($first, $defaultFrom);
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$platforms = [];
if ($metric['per_platform']) {
    $slugs = array_filter(explode(',', input('platforms')));
    $all = StatRepository::platformsWithData((int) $metric['id']);
    $platforms = $slugs ? array_values(array_filter($all, fn($p) => in_array($p['slug'], $slugs, true))) : $all;

    $top = (int) input('top');
    if ($top > 0) {
        $latest = StatRepository::latestPeriod((int) $metric['id'], $device, (int) $country['id']);
        $ranked = array_column(StatRepository::shares((int) $country['id'], (int) $metric['id'], $device, (string) $latest), 'value', 'id');
        usort($platforms, fn($a, $b) => ($ranked[$b['id']] ?? 0) <=> ($ranked[$a['id']] ?? 0));
        $platforms = array_slice(array_filter($platforms, fn($p) => $p['slug'] !== 'other'), 0, $top);
    }
}

$rows = StatRepository::series((int) $country['id'], (int) $metric['id'], $device, $from, $to, array_column($platforms, 'id'));
$periods = StatRepository::periodsBetween($from, $to, $granularity);
$values = [];
foreach ($rows as $r) {
    $values[(int) $r['platform_id']][$r['period']] = (float) $r['value'];
}

$smooth = input('smooth') === '1' && $granularity === 'monthly';
$build = function (int $key) use ($periods, $values, $smooth) {
    $data = array_map(fn($p) => $values[$key][$p] ?? null, $periods);
    return $smooth ? StatRepository::movingAverage($data) : $data;
};

if ($metric['per_platform']) {
    foreach ($platforms as $p) {
        if (!isset($values[$p['id']])) {
            continue;
        }
        $payload['datasets'][] = ['key' => $p['slug'], 'label' => $p['name'], 'color' => $p['color'], 'data' => $build((int) $p['id'])];
    }
} elseif (isset($values[0])) {
    $payload['datasets'][] = ['key' => $metric['code'], 'label' => metric_name($metric), 'color' => '#1f4e79', 'data' => $build(0)];
}

$payload['periods'] = array_map(fn($p) => $granularity === 'yearly' ? substr($p, 0, 4) : substr($p, 0, 7), $periods);
$payload['labels'] = array_map(fn($p) => $granularity === 'yearly' ? substr($p, 0, 4) : fmt_month($p), $periods);
json_response($payload);
