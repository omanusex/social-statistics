<?php
/**
 * <option> list of countries grouped by region.
 * Uses $country (the selected country) from the including page.
 */
$selectedIso = $selectedIso ?? ($country['iso2'] ?? '');
$groups = [];
foreach (StatRepository::countries() as $c) {
    $groups[$c['region']][] = $c;
}
foreach ($groups as $region => $list): ?>
    <optgroup label="<?= e(t('region.' . $region)) ?>">
        <?php foreach ($list as $c): ?>
            <option value="<?= e($c['iso2']) ?>" <?= $c['iso2'] === $selectedIso ? 'selected' : '' ?>><?= e(country_name($c)) ?></option>
        <?php endforeach; ?>
    </optgroup>
<?php endforeach;
unset($selectedIso);
