/* Explore page: load the filtered series, draw chart + table, downloads. */
(function () {
    'use strict';
    const data = window.PAGE_DATA;
    const box = document.getElementById('chartBox');
    const canvas = document.getElementById('mainChart');
    let payload = null;

    // Keep the hidden "platforms" field in sync with the checkboxes
    const hidden = document.getElementById('platforms');
    document.querySelectorAll('.platform-check').forEach((cb) => {
        cb.addEventListener('change', () => {
            hidden.value = [...document.querySelectorAll('.platform-check:checked')].map((c) => c.value).join(',');
        });
    });

    SS.message(box, SS.t('chart.loading'));
    SS.api('series.php', data.params)
        .then((result) => {
            payload = result;
            document.getElementById('sourceName').textContent = result.source;
            if (!result.datasets.length) {
                SS.message(box, SS.t('chart.no_data'));
                return;
            }
            SS.message(box, null);
            SS.seriesChart(canvas, result, data.chart);
            SS.seriesTable(document.getElementById('dataTable'), result);
        })
        .catch((e) => SS.message(box, e.message));

    document.getElementById('btnCsv').addEventListener('click', () => payload && SS.downloadCsv(data.filename, payload));
    document.getElementById('btnPng').addEventListener('click', () => SS.downloadPng(canvas, data.filename));
    document.getElementById('btnLink').addEventListener('click', (e) => SS.copyLink(e.currentTarget));
})();
