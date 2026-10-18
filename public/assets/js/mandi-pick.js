// Shared crop -> mandi picker for the Market Prices pages (alerts, advisory).
// MandiPick.bind({ crop: <select>, mandi: <select>, url: 'index.php?url=mandi/mandis&crop=', texts: {...}, onStatus: fn(msg, type), onReady: fn(), multiple: bool })
// texts: chooseCrop, chooseMandi, chooseCropFirst, loadingMandis, errMandis
(function (root) {
    function fill(sel, first, names) {
        sel.innerHTML = '';
        if (!sel.multiple) { sel.appendChild(new Option(first, '')); }
        (names || []).forEach(function (n) { sel.appendChild(new Option(n, n)); });
    }
    function bind(o) {
        var token = 0;
        function reset() {
            fill(o.mandi, o.texts.chooseCropFirst, []);
            o.mandi.disabled = true;
            if (o.onReady) { o.onReady(); }
        }
        o.crop.addEventListener('change', function () {
            var crop = this.value, mine = ++token;
            reset();
            if (!crop) { o.onStatus && o.onStatus('', ''); return; }
            o.onStatus && o.onStatus(o.texts.loadingMandis, 'info');
            fetch(o.url + encodeURIComponent(crop), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .catch(function () { return { ok: false, error: o.texts.errMandis }; })
                .then(function (j) {
                    if (mine !== token) { return; }
                    if (!j.ok) { o.onStatus && o.onStatus(j.error || o.texts.errMandis, 'danger'); return; }
                    o.onStatus && o.onStatus('', '');
                    fill(o.mandi, o.texts.chooseMandi, j.mandis);
                    o.mandi.disabled = false;
                    if (o.onReady) { o.onReady(); }
                });
        });
        o.mandi.addEventListener('change', function () { if (o.onReady) { o.onReady(); } });
    }
    root.MandiPick = { bind: bind };
})(window);
