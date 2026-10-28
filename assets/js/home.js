/* Home page: share chart, 12-month trend and map of leading platforms. */
(function () {
    'use strict';
    const data = window.PAGE_DATA;

    // Platform shares for the latest month (data comes with the page)
    const sharesBox = document.getElementById('sharesBox');
    if (data.shares.length) {
        SS.barList(document.getElementById('sharesChart'), data.shares, '%');
    } else {
        SS.message(sharesBox, SS.t('chart.no_data'));
    }

    // Trend of the 4 leading platforms over 12 months
    const trendBox = document.getElementById('trendBox');
    SS.message(trendBox, SS.t('chart.loading'));
    SS.api('series.php', { country: data.country, metric: 'traffic_share', device: 'all', top: 4, smooth: 0, from: '', to: '' })
        .then((payload) => {
            const n = payload.labels.length;
            const start = Math.max(0, n - 12);
            payload.labels = payload.labels.slice(start);
            payload.datasets.forEach((d) => { d.data = d.data.slice(start); });
            if (!payload.datasets.length) {
                SS.message(trendBox, SS.t('chart.no_data'));
                return;
            }
            SS.message(trendBox, null);
            SS.seriesChart(document.getElementById('trendChart'), payload, 'line');
        })
        .catch((e) => SS.message(trendBox, e.message));

    // Map: colour each country by its leading platform
    SS.api('map.php', { device: 'all' }).then((result) => {
        const values = {};
        const colors = {};
        Object.entries(result.countries).forEach(([iso, c]) => {
            values[iso] = iso;
            colors[iso] = c.color;
        });
        document.getElementById('mapSub').textContent += ' · ' + result.label;

        const map = new jsVectorMap({
            selector: '#map',
            map: 'world',
            zoomButtons: true,
            regionStyle: { initial: { fill: '#dde3ea', stroke: '#fff', strokeWidth: 0.4 } },
            focusOn: { regions: ['OM', 'EG', 'MA', 'IQ'], animate: false },
            series: { regions: [{ attribute: 'fill', scale: colors, values: values }] },
            onRegionTooltipShow(event, tooltip, code) {
                const c = result.countries[code];
                if (c) {
                    tooltip.text(`${c.name}: ${c.platform} (${c.value.toFixed(1)}%)`);
                }
            },
            onRegionClick(event, code) {
                if (result.countries[code]) {
                    window.location.href = `${window.APP.base}/index.php?country=${code}`;
                }
            },
        });
        window.addEventListener('resize', () => map.updateSize());

        const legend = document.getElementById('mapLegend');
        result.legend.forEach((l) => {
            const item = document.createElement('span');
            item.className = 'me-3 text-nowrap';
            const swatch = document.createElement('span');
            swatch.className = 'legend-swatch';
            swatch.style.background = l.color;
            item.append(swatch, l.platform);
            legend.appendChild(item);
        });
    }).catch((e) => SS.message(document.getElementById('map'), e.message));
})();
