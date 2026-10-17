// Tests for public/assets/js/price-hint.js (F3). Run: node tests/check_price_hint.js
const assert = require('assert');
const P = require('../public/assets/js/price-hint.js');
let fails = 0;
function t(name, fn) { try { fn(); console.log('PASS ' + name); } catch (e) { fails++; console.log('FAIL ' + name + ' -> ' + e.message); } }

t('unit constants', () => {
    assert.strictEqual(P.perQuintalToPerKg(582), 5.82);
    assert.strictEqual(P.perKgToPerQuintal(5.82), 582);
});
t('in line with mandi price', () => assert.strictEqual(P.evaluate(6, 582).status, 'ok'));
t('exactly +30% is still ok', () => assert.strictEqual(P.evaluate(7.566, 582).status, 'ok'));
t('just over +30% is high', () => assert.strictEqual(P.evaluate(7.6, 582).status, 'high'));
t('exactly -30% is still ok', () => assert.strictEqual(P.evaluate(4.074, 582).status, 'ok'));
t('well below is low', () => {
    const r = P.evaluate(3, 582);
    assert.strictEqual(r.status, 'low');
    assert.ok(r.diffPct < -0.4 && r.diffPct > -0.5);
});
t('well above is high', () => assert.strictEqual(P.evaluate(12, 582).status, 'high'));
t('typed quintal price is a unit mix-up', () => {
    const r = P.evaluate(580, 582);
    assert.strictEqual(r.status, 'unit_mixup');
    assert.strictEqual(r.suggestedPerKg, 5.8);
});
t('mix-up window edges (0.5x and 2x of quintal price)', () => {
    assert.strictEqual(P.evaluate(291, 582).status, 'unit_mixup');
    assert.strictEqual(P.evaluate(1164, 582).status, 'unit_mixup');
    assert.notStrictEqual(P.evaluate(290, 582).status, 'unit_mixup');
});
t('string input from a form field works', () => assert.strictEqual(P.evaluate('6.5', '582').status, 'ok'));
t('empty / junk / zero / negative entry -> empty', () => {
    ['', null, undefined, 'abc', 0, -5, NaN, Infinity].forEach(v => assert.strictEqual(P.evaluate(v, 582).status, 'empty', String(v)));
});
t('no usable mandi price -> unknown', () => {
    [0, -1, null, undefined, '', 'x', NaN].forEach(v => assert.strictEqual(P.evaluate(6, v).status, 'unknown', String(v)));
});
t('mandi per kg is reported even before typing', () => assert.strictEqual(P.evaluate('', 582).mandiPerKg, 5.82));

t('fmtRs trims trailing zeros', () => {
    assert.strictEqual(P.fmtRs(582), '582');
    assert.strictEqual(P.fmtRs(5.8), '5.8');
    assert.strictEqual(P.fmtRs(5.8249), '5.82');
});
t('fmtDate', () => {
    assert.strictEqual(P.fmtDate('2026-09-24'), '24 Sep 2026');
    assert.strictEqual(P.fmtDate('2026-01-05'), '5 Jan 2026');
    assert.strictEqual(P.fmtDate('garbage'), '');
    assert.strictEqual(P.fmtDate(null), '');
    assert.strictEqual(P.fmtDate('2026-13-01'), '');
});
t('ageDays', () => {
    assert.strictEqual(P.ageDays('2026-09-24', new Date(Date.UTC(2026, 9, 17))), 23);
    assert.strictEqual(P.ageDays('2026-09-24', new Date(Date.UTC(2026, 8, 24))), 0);
    assert.strictEqual(P.ageDays('2026-09-30', new Date(Date.UTC(2026, 8, 24))), 0);   // future date never negative
    assert.strictEqual(P.ageDays('nope'), null);
});

const info = { mandi: 'Rayya', latestPrice: 582, latestDate: '2026-09-24', ageDays: 23, servedStale: false };
t('describe: reference line shows both units and the date', () => {
    const d = P.describe(info, P.evaluate(6, 582));
    assert.ok(d.reference.includes('Rs 582 per quintal = Rs 5.82 per kg'));
    assert.ok(d.reference.includes('24 Sep 2026') && d.reference.includes('Rayya'));
    assert.strictEqual(d.level, 'success');
});
t('describe: old data is called out', () => {
    assert.ok(P.describe(info, P.evaluate(6, 582)).age.includes('23 days old'));
    assert.strictEqual(P.describe(Object.assign({}, info, { ageDays: 2 }), P.evaluate(6, 582)).age, '');
});
t('describe: stale copy is disclosed', () => {
    assert.ok(P.describe(Object.assign({}, info, { servedStale: true }), P.evaluate(6, 582)).age.includes('saved copy'));
});
t('describe: warnings use the warning level', () => {
    ['high', 'low', 'unit_mixup'].forEach(k => {
        const v = { high: 12, low: 3, unit_mixup: 580 }[k];
        assert.strictEqual(P.describe(info, P.evaluate(v, 582)).level, 'warning', k);
    });
});
t('describe: mix-up text names both numbers', () => {
    const d = P.describe(info, P.evaluate(580, 582));
    assert.ok(d.verdict.includes('Rs 580 per kg') && d.verdict.includes('Rs 5.8 per kg'));
});
t('describe: nothing typed prompts the farmer', () => {
    assert.strictEqual(P.describe(info, P.evaluate('', 582)).verdict, 'Enter your price per kg to compare.');
});

console.log(fails === 0 ? '\nOK: all price-hint checks passed.' : '\n' + fails + ' check(s) FAILED.');
process.exit(fails === 0 ? 0 : 1);
