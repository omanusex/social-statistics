<?php
/**
 * GET api/compare.php
 * The same platform (or country-level metric) over time in several countries.
 *   countries comma separated ISO2 codes (max 6)
 *   metric, platform, device, from, to, smooth - as in series.php
 */
require __DIR__ . '/../includes/bootstrap.php';

$metric = StatRepository::metric(input('metric', 'traffic_share'));
if (!$metric) {
    json_response(['error' => t('error.bad_metric')], 400);
}
$granularity = $metric['granularity'];
$device = $granularity === 'yearly' ? 'all' : input('device', 'all');
if (!in_array($device, StatRepository::DEVICES, true)) {
    json_response(['error' => t('error.bad_device')], 400);
}

$platform = null;
if ($metric['per_platform']) {
    $platform = StatRepository::platform(input('platform', 'facebook'));
    if (!$platform) {
        json_response(['error' => t('error.bad_platform')], 400);
    }
}

$countries = [];
foreach (array_slice(array_filter(explode(',', strtoupper(input('countries', 'OM')))), 0, 6) as $iso) {
    if ($c = StatRepository::country($iso)) {
        $countries[] = $c;
    }
}
if (!$countries) {
    json_response(['error' => t('error.bad_country')], 400);
}

[$first, $last] = StatRepository::periodRange((int) $metric['id']);
$payload = [
    'metric'   => ['code' => $metric['code'], 'name' => metric_name($metric), 'unit' => $metric['unit'], 'granularity' => $granularity],
    'platform' => $platform['name'] ?? null,
    'labels'   => [],
    'periods'  => [],
    'datasets' => [],
    'source'   => $metric['code'] === 'traffic_share' ? 'StatCounter Global Stats' : 'World Bank Open Data',
];
if (!$last) {
    json_response($payload);
}

$defaultFrom = date('Y-m-d', strtotime($granularity === 'yearly' ? '-14 years' : '-23 months', strtotime($last)));
$to = StatRepository::normalisePeriod(input('to'), $granularity) ?? $last;
$from = StatRepository::normalisePeriod(input('from'), $granularity) ?? max($first, $defaultFrom);
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$periods = StatRepository::periodsBetween($from, $to, $granularity);
$smooth = input('smooth') === '1' && $granularity === 'monthly';

// A fixed palette so each country keeps its colour; the default country (Oman) is always brand blue
$palette = ['#c0392b', '#27ae60', '#8e44ad', '#e67e22', '#16a085', '#7f8c8d'];
foreach ($countries as $i => $country) {
    $rows = StatRepository::series((int) $country['id'], (int) $metric['id'], $device, $from, $to, $platform ? [(int) $platform['id']] : []);
    $byPeriod = [];
    foreach ($rows as $r) {
        if ($platform || $r['platform_id'] === null) {
            $byPeriod[$r['period']] = (float) $r['value'];
        }
    }
    $data = array_map(fn($p) => $byPeriod[$p] ?? null, $periods);
    $payload['datasets'][] = [
        'key'       => $country['iso2'],
        'label'     => country_name($country),
        'color'     => $country['is_default'] ? '#1f4e79' : $palette[$i % count($palette)],
        'highlight' => (bool) $country['is_default'],
        'data'      => $smooth ? StatRepository::movingAverage($data) : $data,
    ];
}

$payload['periods'] = array_map(fn($p) => $granularity === 'yearly' ? substr($p, 0, 4) : substr($p, 0, 7), $periods);
$payload['labels'] = array_map(fn($p) => $granularity === 'yearly' ? substr($p, 0, 4) : fmt_month($p), $periods);
json_response($payload);
