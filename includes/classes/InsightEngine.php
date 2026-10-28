<?php

/**
 * Generates short written findings from the data with simple rules (no AI involved).
 * Each insight is ['icon' => ..., 'text' => ...] in the current language.
 */
class InsightEngine
{
    private array $country;
    private array $metric;

    public function __construct(array $country)
    {
        $this->country = $country;
        $this->metric = StatRepository::metric('traffic_share');
    }

    public function generate(): array
    {
        $latest = StatRepository::latestPeriod((int) $this->metric['id'], 'all', (int) $this->country['id']);
        $insights = [];
        if ($latest) {
            foreach (['leader', 'trend', 'devices', 'gcc'] as $rule) {
                $insight = $this->$rule($latest);
                if ($insight) {
                    $insights[] = $insight;
                }
            }
        }
        $internet = $this->internet();
        if ($internet) {
            $insights[] = $internet;
        }
        return $insights;
    }

    private function name(): string
    {
        return country_name($this->country);
    }

    private function shares(string $device, string $period): array
    {
        return array_values(array_filter(
            StatRepository::shares((int) $this->country['id'], (int) $this->metric['id'], $device, $period),
            fn($r) => $r['slug'] !== 'other'
        ));
    }

    /** Which platform sent the most traffic last month. */
    private function leader(string $period): ?array
    {
        $shares = $this->shares('all', $period);
        if (!$shares) {
            return null;
        }
        $text = t('insight.leader', [
            'month' => fmt_month($period), 'platform' => $shares[0]['name'],
            'country' => $this->name(), 'value' => number_format($shares[0]['value'], 1),
        ]);
        if (isset($shares[1])) {
            $text .= ' ' . t('insight.second', ['platform' => $shares[1]['name'], 'value' => number_format($shares[1]['value'], 1)]);
        }
        return ['icon' => 'trophy', 'text' => $text];
    }

    /** Biggest change between the average of the last 3 months and the 3 months half a year earlier. */
    private function trend(string $period): ?array
    {
        $from = date('Y-m-01', strtotime('-8 months', strtotime($period)));
        $rows = StatRepository::series((int) $this->country['id'], (int) $this->metric['id'], 'all', $from, $period);
        $byPlatform = [];
        foreach ($rows as $r) {
            $byPlatform[$r['platform_id']][$r['period']] = (float) $r['value'];
        }
        $recentMonths = [date('Y-m-01', strtotime('-2 months', strtotime($period))), date('Y-m-01', strtotime('-1 month', strtotime($period))), $period];
        $oldMonths = array_map(fn($m) => date('Y-m-01', strtotime('-6 months', strtotime($m))), $recentMonths);

        $best = null;
        foreach ($byPlatform as $platformId => $values) {
            $recent = array_intersect_key($values, array_flip($recentMonths));
            $old = array_intersect_key($values, array_flip($oldMonths));
            if (count($recent) < 2 || count($old) < 2) {
                continue;
            }
            $change = array_sum($recent) / count($recent) - array_sum($old) / count($old);
            if (!$best || abs($change) > abs($best['change'])) {
                $best = ['platform_id' => $platformId, 'change' => $change];
            }
        }
        if (!$best || abs($best['change']) < 1) {
            return null;
        }
        $platform = Database::one('SELECT name FROM platforms WHERE id = ?', [$best['platform_id']]);
        $key = $best['change'] > 0 ? 'insight.rise' : 'insight.fall';
        return [
            'icon' => $best['change'] > 0 ? 'graph-up-arrow' : 'graph-down-arrow',
            'text' => t($key, ['platform' => $platform['name'], 'points' => number_format(abs($best['change']), 1), 'country' => $this->name()]),
        ];
    }

    /** Different leader on mobile and on desktop. */
    private function devices(string $period): ?array
    {
        $mobile = $this->shares('mobile', $period);
        $desktop = $this->shares('desktop', $period);
        if (!$mobile || !$desktop) {
            return null;
        }
        $key = $mobile[0]['slug'] === $desktop[0]['slug'] ? 'insight.devices_same' : 'insight.devices_diff';
        return ['icon' => 'phone', 'text' => t($key, [
            'mobile' => $mobile[0]['name'], 'mvalue' => number_format($mobile[0]['value'], 1),
            'desktop' => $desktop[0]['name'], 'dvalue' => number_format($desktop[0]['value'], 1),
        ])];
    }

    /** Compare the leading platform with the GCC average. */
    private function gcc(string $period): ?array
    {
        $shares = $this->shares('all', $period);
        if (!$shares) {
            return null;
        }
        $platform = $shares[0];
        $values = StatRepository::valuesByCountry((int) $this->metric['id'], (int) $platform['id'], 'all', $period);
        $gcc = array_filter($values, fn($r) => $r['region'] === 'GCC' && $r['iso2'] !== $this->country['iso2']);
        if (count($gcc) < 2) {
            return null;
        }
        $avg = array_sum(array_column($gcc, 'value')) / count($gcc);
        $diff = $platform['value'] - $avg;
        $key = $diff >= 0 ? 'insight.gcc_above' : 'insight.gcc_below';
        return ['icon' => 'globe2', 'text' => t($key, [
            'platform' => $platform['name'], 'country' => $this->name(),
            'points' => number_format(abs($diff), 1), 'avg' => number_format($avg, 1),
        ])];
    }

    /** Internet use from the World Bank. */
    private function internet(): ?array
    {
        $row = StatRepository::latestCountryValue((int) $this->country['id'], 'internet_users');
        if (!$row) {
            return null;
        }
        return ['icon' => 'wifi', 'text' => t('insight.internet', [
            'value' => number_format($row['value'], 1), 'country' => $this->name(), 'year' => substr($row['period'], 0, 4),
        ])];
    }
}
