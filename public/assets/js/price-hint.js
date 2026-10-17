/**
 * Price hint for the Sell Produce form (F3).
 *
 * Units: listings are priced in Rs per KG, the mandi service reports Rs per
 * QUINTAL (1 quintal = 100 kg). All conversion lives here so it can be tested
 * with Node (tests/check_price_hint.js) and so views never repeat the "x 100".
 *
 * Pure functions only: no DOM access, no network.
 */
(function (root) {
    'use strict';

    var KG_PER_QUINTAL = 100;
    var TOLERANCE = 0.30;      // within +/-30% of the mandi price counts as "in line"
    var OLD_DATA_DAYS = 7;     // matches the mandi service's stale-data threshold
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function isPositiveNumber(n) {
        return typeof n === 'number' && isFinite(n) && n > 0;
    }

    function toNumber(v) {
        if (typeof v === 'number') { return v; }
        if (typeof v === 'string' && v.trim() !== '') { return Number(v); }
        return NaN;
    }

    function perQuintalToPerKg(q) { return q / KG_PER_QUINTAL; }
    function perKgToPerQuintal(k) { return k * KG_PER_QUINTAL; }

    /**
     * Compare what the farmer typed (Rs/kg) with the latest mandi price (Rs/quintal).
     *
     * Returns { status, diffPct, mandiPerKg, enteredPerKg, enteredPerQuintal, suggestedPerKg }
     * status is one of:
     *   'empty'       nothing (valid) typed yet
     *   'unknown'     no usable mandi price
     *   'unit_mixup'  the typed number is close to the per-QUINTAL price, so it
     *                 was probably typed in the wrong unit
     *   'ok'          within TOLERANCE of the mandi price
     *   'high'        more than TOLERANCE above
     *   'low'         more than TOLERANCE below
     */
    function evaluate(enteredPerKg, mandiPerQuintal) {
        var entered = toNumber(enteredPerKg);
        var mandi = toNumber(mandiPerQuintal);
        var out = { status: 'empty', diffPct: null, mandiPerKg: null, enteredPerKg: null, enteredPerQuintal: null, suggestedPerKg: null };

        if (!isPositiveNumber(mandi)) {
            out.status = 'unknown';
            return out;
        }
        out.mandiPerKg = perQuintalToPerKg(mandi);
        if (!isPositiveNumber(entered)) {
            return out;
        }
        out.enteredPerKg = entered;
        out.enteredPerQuintal = perKgToPerQuintal(entered);

        // A real per-kg price is ~1/100th of the quintal price. If the typed
        // number is within half to double of the QUINTAL price, the unit is wrong.
        var ratioToQuintal = entered / mandi;
        if (ratioToQuintal >= 0.5 && ratioToQuintal <= 2) {
            out.status = 'unit_mixup';
            out.suggestedPerKg = perQuintalToPerKg(entered);
            return out;
        }

        // Round to 4 decimals so floating-point noise (e.g. 0.30000000000000016)
        // cannot push a price that is exactly on the limit over it.
        out.diffPct = Math.round(((out.enteredPerQuintal - mandi) / mandi) * 10000) / 10000;
        if (Math.abs(out.diffPct) <= TOLERANCE) {
            out.status = 'ok';
        } else {
            out.status = out.diffPct > 0 ? 'high' : 'low';
        }
        return out;
    }

    /** Rs amount with at most 2 decimals, no trailing zeros ("5.82", "582", "5.8"). */
    function fmtRs(n) {
        return String(Math.round(n * 100) / 100);
    }

    /** '2026-09-24' -> '24 Sep 2026'. Returns '' for anything unparseable. */
    function fmtDate(iso) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || ''));
        if (!m) { return ''; }
        var mon = Number(m[2]);
        if (mon < 1 || mon > 12) { return ''; }
        return Number(m[3]) + ' ' + MONTHS[mon - 1] + ' ' + m[1];
    }

    /** Days since an ISO date (UTC), or null. `now` is injectable for tests. */
    function ageDays(iso, now) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso || ''));
        if (!m) { return null; }
        var then = Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        var n = now instanceof Date ? now : new Date();
        var today = Date.UTC(n.getUTCFullYear(), n.getUTCMonth(), n.getUTCDate());
        return Math.max(0, Math.round((today - then) / 86400000));
    }

    /**
     * Build the lines to show. Returns { reference, age, verdict, level }.
     * level: 'info' | 'success' | 'warning' (bootstrap alert colours).
     * Text only; the caller must set it with textContent.
     */
    function describe(info, ev) {
        var q = info.latestPrice;
        var ref = 'Latest mandi price at ' + info.mandi + (info.latestDate ? ' (' + fmtDate(info.latestDate) + ')' : '') +
            ': Rs ' + fmtRs(q) + ' per quintal = Rs ' + fmtRs(perQuintalToPerKg(q)) + ' per kg.';
        var age = '';
        var days = info.ageDays;
        if (typeof days === 'number' && days >= OLD_DATA_DAYS) {
            age = 'This price is ' + days + ' days old, so treat it as a rough guide only.';
        }
        if (info.servedStale) {
            age = (age ? age + ' ' : '') + 'The price service could not be reached, so this is a saved copy.';
        }

        var verdict = '';
        var level = 'info';
        switch (ev.status) {
            case 'unit_mixup':
                verdict = 'Rs ' + fmtRs(ev.enteredPerKg) + ' per kg is close to the per-QUINTAL price. ' +
                    'This field is per KG. Did you mean Rs ' + fmtRs(ev.suggestedPerKg) + ' per kg?';
                level = 'warning';
                break;
            case 'ok':
                verdict = 'Your price is within ' + Math.round(TOLERANCE * 100) + '% of the mandi price.';
                level = 'success';
                break;
            case 'high':
                verdict = 'Your price is ' + Math.round(ev.diffPct * 100) + '% above the mandi price. Buyers may choose cheaper sellers.';
                level = 'warning';
                break;
            case 'low':
                verdict = 'Your price is ' + Math.round(Math.abs(ev.diffPct) * 100) + '% below the mandi price. You may be selling too cheap.';
                level = 'warning';
                break;
            default:
                verdict = 'Enter your price per kg to compare.';
        }
        return { reference: ref, age: age, verdict: verdict, level: level };
    }

    var api = {
        KG_PER_QUINTAL: KG_PER_QUINTAL,
        TOLERANCE: TOLERANCE,
        perQuintalToPerKg: perQuintalToPerKg,
        perKgToPerQuintal: perKgToPerQuintal,
        evaluate: evaluate,
        describe: describe,
        fmtRs: fmtRs,
        fmtDate: fmtDate,
        ageDays: ageDays
    };

    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
    root.PriceHint = api;
})(typeof window !== 'undefined' ? window : globalThis);
