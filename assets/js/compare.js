/* Compare page: multi-country chart and the internet-use scatter chart. */
(function () {
    'use strict';
    const data = window.PAGE_DATA;
    const box = document.getElementById('chartBox');
    const canvas = document.getElementById('mainChart');
    let payload = null;

    // Country checkboxes (max 6) fill the hidden "countries" field
    const hidden = document.getElementById('countries');
    const checks = [...document.querySelectorAll('.country-check')];
    const sync = () => {
        const chosen = checks.filter((c) => c.checked);
        hidden.value = chosen.map((c) => c.value).join(',');
        checks.forEach((c) => { c.disabled = !c.checked && chosen.length >= 6; });
    };
    checks.forEach((c) => c.addEventListener('change', sync));
    sync();

    SS.message(box, SS.t('chart.loading'));
    SS.api('compare.php', data.params)
        .then((result) => {
            payload = result;
            if (!result.datasets.some((d) => d.data.some((v) => v !== null))) {
                SS.message(box, SS.t('chart.no_data'));
                return;
            }
            SS.message(box, null);
            SS.seriesChart(canvas, result, 'line');
        })
        .catch((e) => SS.message(box, e.message));

    document.getElementById('btnCsv').addEventListener('click', () => payload && SS.downloadCsv(data.filename, payload));
    document.getElementById('btnPng').addEventListener('click', () => SS.downloadPng(canvas, data.filename));
    document.getElementById('btnLink').addEventListener('click', (e) => SS.copyLink(e.currentTarget));

    // Scatter: internet users (x) against platform share (y), one point per country
    const scatter = document.getElementById('scatter');
    if (scatter && data.scatter.length) {
        SS.draw(scatter, {
            type: 'scatter',
            data: {
                datasets: [{
                    data: data.scatter,
                    backgroundColor: data.scatter.map((p) => (p.highlight ? '#1f4e79' : '#2e86ab88')),
                    pointRadius: data.scatter.map((p) => (p.highlight ? 8 : 5)),
                }],
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => `${c.raw.label}: ${c.raw.x.toFixed(1)}% (${c.raw.year}), ${c.raw.y.toFixed(1)}%` } },
                },
                scales: {
                    x: { title: { display: true, text: data.scatterAxes.x }, ticks: { callback: (v) => v + '%' } },
                    y: { title: { display: true, text: data.scatterAxes.y }, beginAtZero: true, ticks: { callback: (v) => v + '%' } },
                },
            },
        });
    } else if (scatter) {
        SS.message(document.getElementById('scatterBox'), SS.t('chart.no_data'));
    }
})();
