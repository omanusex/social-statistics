/* Local study page charts (data is embedded in the page). */
(function () {
    'use strict';
    const data = window.PAGE_DATA;
    const el = (id) => document.getElementById(id);
    if (!el('usageChart')) return;

    SS.barList(el('usageChart'), data.usage, '%');
    SS.doughnut(el('favouriteChart'), data.favourite);

    const vertical = (canvas, items) => SS.draw(canvas, {
        type: 'bar',
        data: {
            labels: items.map((i) => i.label),
            datasets: [{ data: items.map((i) => i.value), backgroundColor: '#2e86ab', borderRadius: 4, maxBarThickness: 40 }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } },
        },
    });
    vertical(el('purposeChart'), data.purpose);
    vertical(el('hoursChart'), data.hours);
    vertical(el('ageChart'), data.age);
})();
