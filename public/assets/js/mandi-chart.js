// Price chart helpers for the Market Prices page (F1).
// buildSeries() is pure so it can be tested with Node (tests/check_mandi_chart.js).
// draw() needs the Chart.js global and a canvas; it is only used in the browser.
(function (root) {
    'use strict';

    // history: [{date, price}] oldest first. future: [{date, price}] projected rows.
    // Returns labels plus two aligned value arrays (null where a line has no point).
    // The projection line starts at the last reported point so the two lines join.
    function buildSeries(history, future) {
        var past = (history || []).filter(function (p) {
            return p && typeof p.date === 'string' && isFinite(p.price);
        }).sort(function (a, b) { return a.date < b.date ? -1 : a.date > b.date ? 1 : 0; });
        var lastDate = past.length ? past[past.length - 1].date : '';
        var proj = (future || []).filter(function (p) {
            return p && typeof p.date === 'string' && isFinite(p.price) && p.date > lastDate;
        }).sort(function (a, b) { return a.date < b.date ? -1 : a.date > b.date ? 1 : 0; });

        var labels = past.map(function (p) { return p.date; }).concat(proj.map(function (p) { return p.date; }));
        var hist = past.map(function (p) { return Number(p.price); });
        var fc = past.map(function () { return null; });
        if (past.length && proj.length) {
            fc[fc.length - 1] = Number(past[past.length - 1].price);
        }
        proj.forEach(function (p) {
            hist.push(null);
            fc.push(Number(p.price));
        });
        return { labels: labels, history: hist, forecast: fc, pastCount: past.length, projCount: proj.length };
    }

    // Draws (or redraws) the chart. `chart` is the previous Chart instance, if any.
    function draw(Chart, canvas, series, text, chart) {
        if (chart) { chart.destroy(); }
        return new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: series.labels,
                datasets: [
                    { label: text.history, data: series.history, borderColor: '#2e7d32', backgroundColor: '#2e7d32',
                      borderWidth: 2, pointRadius: 1.5, tension: 0.2, spanGaps: false },
                    { label: text.forecast, data: series.forecast, borderColor: '#f57c00', backgroundColor: '#f57c00',
                      borderWidth: 2, borderDash: [6, 4], pointRadius: 3, tension: 0.2, spanGaps: false }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: { ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                    y: { title: { display: true, text: text.unit } }
                }
            }
        });
    }

    var api = { buildSeries: buildSeries, draw: draw };
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
    root.MandiChart = api;
})(typeof window !== 'undefined' ? window : globalThis);
