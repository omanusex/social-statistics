/* Shared chart and data helpers used by every page (window.SS). */
(function () {
    'use strict';

    const t = (key) => (window.APP.i18n && window.APP.i18n[key]) || key;
    const charts = new Map();

    if (window.Chart) {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.font.size = 13;
        Chart.defaults.color = '#475467';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.rtl = window.APP.rtl;
        Chart.defaults.plugins.tooltip.rtl = window.APP.rtl;
        Chart.defaults.maintainAspectRatio = false;
    }

    /** GET a JSON endpoint inside /api with query parameters. */
    async function api(path, params) {
        const query = new URLSearchParams(params || {}).toString();
        const response = await fetch(`${window.APP.base}/api/${path}?${query}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || t('chart.error'));
        }
        return data;
    }

    /** Show a message (loading / no data / error) over a chart box. */
    function message(box, text) {
        let el = box.querySelector('.chart-message');
        if (!text) {
            if (el) el.remove();
            return;
        }
        if (!el) {
            el = document.createElement('div');
            el.className = 'chart-message';
            box.appendChild(el);
        }
        el.textContent = text;
    }

    function formatValue(value, unit) {
        if (value === null || value === undefined) return '–';
        if (unit === '%') return Math.round(Number(value) * 10) / 10 + '%';
        if (value >= 1e6) return (value / 1e6).toFixed(1) + 'M';
        return Number(value).toLocaleString();
    }

    /** Draw (or redraw) a chart in a canvas. */
    function draw(canvas, config) {
        if (charts.has(canvas)) {
            charts.get(canvas).destroy();
        }
        const chart = new Chart(canvas, config);
        charts.set(canvas, chart);
        return chart;
    }

    /** Line or bar chart from an API "series" payload. */
    function seriesChart(canvas, payload, type) {
        const unit = payload.metric.unit;
        const isBar = type === 'bar';
        return draw(canvas, {
            type: isBar ? 'bar' : 'line',
            data: {
                labels: payload.labels,
                datasets: payload.datasets.map((d) => ({
                    label: d.label,
                    data: d.data,
                    borderColor: d.color,
                    backgroundColor: isBar ? d.color : d.color + '22',
                    borderWidth: d.highlight ? 3.5 : 2,
                    pointRadius: payload.labels.length > 24 ? 0 : 3,
                    pointHoverRadius: 5,
                    tension: 0.25,
                    spanGaps: true,
                })),
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (v) => formatValue(v, unit) },
                    },
                    x: { grid: { display: false } },
                },
                plugins: {
                    tooltip: {
                        callbacks: { label: (c) => `${c.dataset.label}: ${formatValue(c.parsed.y, unit)}` },
                    },
                },
            },
        });
    }

    /** Horizontal bar chart from [{label, value, color}]. */
    function barList(canvas, items, unit, options) {
        return draw(canvas, {
            type: 'bar',
            data: {
                labels: items.map((i) => i.label),
                datasets: [{
                    data: items.map((i) => i.value),
                    backgroundColor: items.map((i) => i.color || '#1f4e79'),
                    borderRadius: 4,
                    maxBarThickness: 28,
                }],
            },
            options: Object.assign({
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => formatValue(c.parsed.x, unit) } },
                },
                scales: {
                    x: { beginAtZero: true, ticks: { callback: (v) => formatValue(v, unit) } },
                    y: { grid: { display: false }, position: window.APP.rtl ? 'right' : 'left' },
                },
            }, options || {}),
        });
    }

    function doughnut(canvas, items) {
        return draw(canvas, {
            type: 'doughnut',
            data: {
                labels: items.map((i) => i.label),
                datasets: [{ data: items.map((i) => i.value), backgroundColor: items.map((i) => i.color), borderWidth: 2 }],
            },
            options: { cutout: '58%', plugins: { legend: { position: 'bottom' } } },
        });
    }

    /** Build an HTML table body from a series payload. */
    function seriesTable(table, payload) {
        const head = table.querySelector('thead');
        const body = table.querySelector('tbody');
        const unit = payload.metric.unit;
        head.innerHTML = '';
        body.innerHTML = '';
        const tr = document.createElement('tr');
        [t('chart.period'), ...payload.datasets.map((d) => d.label)].forEach((text) => {
            const th = document.createElement('th');
            th.textContent = text;
            tr.appendChild(th);
        });
        head.appendChild(tr);
        payload.labels.slice().reverse().forEach((label, ri) => {
            const i = payload.labels.length - 1 - ri;
            const row = document.createElement('tr');
            const cells = [label, ...payload.datasets.map((d) => formatValue(d.data[i], unit))];
            cells.forEach((text) => {
                const td = document.createElement('td');
                td.textContent = text;
                row.appendChild(td);
            });
            body.appendChild(row);
        });
    }

    /** Download a series payload as a CSV file (opens in Excel). */
    function downloadCsv(filename, payload) {
        const quote = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
        const lines = [['period', ...payload.datasets.map((d) => d.label)].map(quote).join(',')];
        payload.periods.forEach((period, i) => {
            lines.push([period, ...payload.datasets.map((d) => d.data[i] ?? '')].map(quote).join(','));
        });
        lines.push('');
        lines.push(quote('Source: ' + payload.source));
        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        saveBlob(blob, filename + '.csv');
    }

    function downloadPng(canvas, filename) {
        const chart = charts.get(canvas);
        if (!chart) return;
        // white background so the image is readable when pasted into a report
        const copy = document.createElement('canvas');
        copy.width = canvas.width;
        copy.height = canvas.height;
        const ctx = copy.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, copy.width, copy.height);
        ctx.drawImage(canvas, 0, 0);
        copy.toBlob((blob) => saveBlob(blob, filename + '.png'));
    }

    function saveBlob(blob, name) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }

    async function copyLink(button) {
        try {
            await navigator.clipboard.writeText(window.location.href);
            const old = button.innerHTML;
            button.textContent = t('chart.copied');
            setTimeout(() => { button.innerHTML = old; }, 1500);
        } catch (e) {
            window.prompt('', window.location.href);
        }
    }

    window.SS = { t, api, message, formatValue, seriesChart, barList, doughnut, seriesTable, downloadCsv, downloadPng, copyLink, draw };
})();
