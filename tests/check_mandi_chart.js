// Tests for public/assets/js/mandi-chart.js (F1). Run: node tests/check_mandi_chart.js
const assert = require('assert');
const C = require('../public/assets/js/mandi-chart.js');
let fails = 0;
function t(name, fn) { try { fn(); console.log('PASS ' + name); } catch (e) { fails++; console.log('FAIL ' + name + ' -> ' + e.message); } }

const hist = [{ date: '2026-09-23', price: 580 }, { date: '2026-09-22', price: 575 }, { date: '2026-09-24', price: 582 }];

t('history only: sorted, no projection', () => {
    const s = C.buildSeries(hist, []);
    assert.deepStrictEqual(s.labels, ['2026-09-22', '2026-09-23', '2026-09-24']);
    assert.deepStrictEqual(s.history, [575, 580, 582]);
    assert.deepStrictEqual(s.forecast, [null, null, null]);
    assert.strictEqual(s.projCount, 0);
});
t('projection joins the last reported point', () => {
    const s = C.buildSeries(hist, [{ date: '2026-09-25', price: 584 }, { date: '2026-09-26', price: 585 }]);
    assert.deepStrictEqual(s.labels.slice(-2), ['2026-09-25', '2026-09-26']);
    assert.deepStrictEqual(s.history, [575, 580, 582, null, null]);
    assert.deepStrictEqual(s.forecast, [null, null, 582, 584, 585]);
});
t('projected dates not after the last reported day are dropped', () => {
    const s = C.buildSeries(hist, [{ date: '2026-09-24', price: 999 }, { date: '2026-09-20', price: 1 }]);
    assert.strictEqual(s.projCount, 0);
    assert.deepStrictEqual(s.forecast, [null, null, null]);
});
t('bad points are ignored', () => {
    const s = C.buildSeries([{ date: '2026-09-24', price: 'abc' }, { date: null, price: 5 }, null, { date: '2026-09-25', price: 600 }], null);
    assert.deepStrictEqual(s.labels, ['2026-09-25']);
    assert.deepStrictEqual(s.history, [600]);
});
t('empty history with projection draws only the projection', () => {
    const s = C.buildSeries([], [{ date: '2026-10-20', price: 590 }]);
    assert.strictEqual(s.pastCount, 0);
    assert.deepStrictEqual(s.forecast, [590]);
    assert.deepStrictEqual(s.history, [null]);
});
t('empty input is safe', () => {
    const s = C.buildSeries(undefined, undefined);
    assert.deepStrictEqual(s.labels, []);
});
process.exit(fails ? 1 : 0);
