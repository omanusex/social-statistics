<?php
/**
 * Public page footer. A page may set $pageScripts (list of asset paths) and
 * $pageData (array passed to JavaScript as window.PAGE_DATA).
 */
?>
    </div>
</main>

<footer class="site-footer mt-5">
    <div class="container py-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-7">
                <strong><?= e(t('site.name')) ?></strong>
                <div class="small text-muted"><?= e(t('footer.purpose')) ?></div>
            </div>
            <div class="col-md-5 small text-md-end text-muted">
                <?= e(t('footer.data')) ?>
                <a href="https://gs.statcounter.com/social-media-stats" target="_blank" rel="noopener">StatCounter Global Stats</a> (CC BY-SA 3.0) ·
                <a href="https://data.worldbank.org" target="_blank" rel="noopener">World Bank</a> (CC BY 4.0)
            </div>
        </div>
    </div>
</footer>

<script>
    window.APP = <?= json_for_script([
        'base' => base_path(),
        'lang' => Lang::current(),
        'rtl'  => Lang::dir() === 'rtl',
        'i18n' => Lang::forJs([
            'chart.no_data', 'chart.loading', 'chart.period', 'chart.download_csv', 'chart.copied',
            'chart.error', 'chart.share', 'chart.country', 'chart.value',
        ]),
    ]) ?>;
    window.PAGE_DATA = <?= json_for_script($pageData ?? new stdClass()) ?>;
</script>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<?php foreach ($pageScripts ?? [] as $script): ?>
    <script src="<?= asset($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
