<?php
/**
 * GET api/map.php
 * Leading social platform in every country for one month (used by the map on the home page).
 *   device  all | mobile | desktop
 *   period  "YYYY-MM" (default: latest month)
 */
require __DIR__ . '/../includes/bootstrap.php';

$metric = StatRepository::metric('traffic_share');
$device = input('device', 'all');
if (!in_array($device, StatRepository::DEVICES, true)) {
    json_response(['error' => t('error.bad_device')], 400);
}
$period = StatRepository::normalisePeriod(input('period'), 'monthly')
    ?? StatRepository::latestPeriod((int) $metric['id'], $device);

$countries = [];
$legend = [];
foreach ($period ? StatRepository::topPlatformPerCountry((int) $metric['id'], $device, $period) : [] as $row) {
    if ($row['iso2'] === 'WW') {
        continue; // "Worldwide" is not a place on the map
    }
    $countries[$row['iso2']] = [
        'name'     => Lang::current() === 'ar' ? $row['name_ar'] : $row['name_en'],
        'platform' => $row['platform'],
        'value'    => (float) $row['value'],
        'color'    => $row['color'],
    ];
    $legend[$row['slug']] = ['platform' => $row['platform'], 'color' => $row['color']];
}

json_response([
    'period'    => $period,
    'label'     => fmt_month($period),
    'countries' => $countries,
    'legend'    => array_values($legend),
]);
