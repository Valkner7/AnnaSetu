<?php
// Price alerts page. Variables from AlertsController::index(): $crops, $myAlerts, $newlyReached, $maxActive.
$mandiActive = 'alerts';
$t = function ($k) { return __($k); };
$money = function ($n) { return '₹' . number_format((float) $n, 2, '.', ','); };
$activeCount = 0;
foreach ($myAlerts as $a) { if ($a['status'] === 'active') { $activeCount++; } }
?>
<div class="container py-5">
    <div class="mb-3">
        <h2 class="fw-bold" style="color: var(--primary-green);"><i class="fa-solid fa-bell"></i> <?= e(__('al_title')) ?></h2>
        <p class="text-muted mb-3"><?= e(__('al_desc')) ?></p>
    </div>
    <?php require __DIR__ . '/_nav.php'; ?>

    <?php if ($newlyReached > 0): ?>
        <div class="alert alert-success" role="status"><i class="fa-solid fa-circle-check"></i> <?= e(\App\Helpers\MandiText::fill(__('al_new_banner'), ['n' => $newlyReached])) ?></div>
    <?php endif; ?>
    <div id="status" class="alert d-none" role="alert"></div>

    <div class="card shadow-sm border-0 mb-4" style="border-top: 3px solid var(--primary-orange);">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><?= e(__('al_new')) ?></h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="crop"><?= e(__('mp_crop')) ?></label>
                    <select class="form-select" id="crop">
                        <option value=""><?= e(__('mp_choose_crop')) ?></option>
                        <?php foreach ($crops as $c): ?>
                            <option value="<?= e($c) ?>"><?= e(\App\Helpers\MandiText::cropLabel($c, $t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="mandi"><?= e(__('mp_mandi')) ?></label>
                    <select class="form-select" id="mandi" disabled><option value=""><?= e(__('mp_choose_crop_first')) ?></option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="direction"><?= e(__('al_direction')) ?></label>
                    <select class="form-select" id="direction">
                        <option value="above"><?= e(__('al_above')) ?></option>
                        <option value="below"><?= e(__('al_below')) ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="target"><?= e(__('al_target')) ?></label>
                    <input type="number" class="form-control" id="target" min="1" max="200000" step="0.01" inputmode="decimal">
                </div>
            </div>
            <button type="button" class="btn btn-primary-custom fw-bold mt-3" id="create" disabled><?= e(__('al_create')) ?></button>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
        <h5 class="fw-bold mb-0"><?= e(__('al_your')) ?> <span class="text-muted fw-normal small">(<?= e((int) $activeCount) ?>/<?= e((int) $maxActive) ?>)</span></h5>
        <button type="button" class="btn btn-outline-success btn-sm" id="checkNow" <?= e($activeCount === 0 ? 'disabled' : '') ?>><i class="fa-solid fa-rotate"></i> <?= e(__('al_check_now')) ?></button>
    </div>

    <?php if (!$myAlerts): ?>
        <p class="text-muted"><?= e(__('al_none')) ?></p>
    <?php else: ?>
        <div class="list-group shadow-sm">
            <?php foreach ($myAlerts as $a):
                $reached = $a['status'] === 'triggered';
                $rule = \App\Helpers\MandiText::fill(__('al_rule'), [
                    'crop' => \App\Helpers\MandiText::cropLabel($a['crop'], $t),
                    'mandi' => $a['mandi'],
                    'dir' => __($a['direction'] === 'above' ? 'al_above' : 'al_below'),
                    'target' => number_format((float) $a['target_price'], 2, '.', ','),
                ]);
            ?>
                <div class="list-group-item <?= e($reached ? 'list-group-item-success' : '') ?>">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-bold"><?= e($rule) ?></div>
                            <div class="small text-muted">
                                <?php if ($reached): ?>
                                    <?= e(\App\Helpers\MandiText::fill(__('al_reached'), ['price' => number_format((float) $a['triggered_price'], 2, '.', ','), 'date' => $a['triggered_price_date']])) ?>
                                <?php elseif ($a['last_price'] !== null): ?>
                                    <?= e(\App\Helpers\MandiText::fill(__('al_last'), ['price' => number_format((float) $a['last_price'], 2, '.', ','), 'date' => $a['last_price_date']])) ?>
                                <?php else: ?>
                                    <?= e(__('al_not_checked')) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= e($reached ? 'bg-success' : 'bg-secondary') ?>"><?= e(__($reached ? 'al_status_triggered' : 'al_status_active')) ?></span>
                            <?php if ($reached): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm js-rearm" data-id="<?= e((int) $a['id']) ?>"><?= e(__('al_rearm')) ?></button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-danger btn-sm js-delete" data-id="<?= e((int) $a['id']) ?>" aria-label="<?= e(__('al_delete')) ?>"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="text-muted small mt-3 mb-0"><?= e(__('al_how')) ?></p>
</div>
<script src="<?= e(BASE_URL) ?>/assets/js/mandi-pick.js?v=1"></script>
<script>
(function () {
    var BASE = <?= json_encode(BASE_URL . '/index.php?url=', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var T = <?= json_encode([
        'chooseMandi' => __('mp_choose_mandi'), 'chooseCropFirst' => __('mp_choose_crop_first'),
        'loadingMandis' => __('mp_loading_mandis'), 'errMandis' => __('mp_err_mandis'),
        'checking' => __('al_checking'), 'checkDone' => __('al_check_done'), 'checkUnavail' => __('al_check_unavail'),
        'created' => __('al_created'), 'deleted' => __('al_deleted'), 'confirmDelete' => __('al_confirm_delete'),
        'err' => __('al_err_generic'), 'errRead' => __('mp_err_read'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    function el(id) { return document.getElementById(id); }
    function fmt(text, vars) { return String(text).replace(/\{(\w+)\}/g, function (m, k) { return vars[k] === undefined ? m : vars[k]; }); }
    function status(msg, type) {
        var s = el('status');
        if (!msg) { s.className = 'alert d-none'; s.textContent = ''; return; }
        s.className = 'alert alert-' + type;
        s.textContent = msg;
    }
    function post(action, fields) {
        var body = new URLSearchParams();
        Object.keys(fields || {}).forEach(function (k) { body.append(k, fields[k]); });
        return fetch(BASE + 'alerts/' + action, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, error: T.errRead }; });
    }
    function ready() {
        var t = parseFloat(el('target').value);
        el('create').disabled = !(el('crop').value && el('mandi').value && t > 0);
    }
    MandiPick.bind({ crop: el('crop'), mandi: el('mandi'), url: BASE + 'mandi/mandis&crop=', texts: T, onStatus: status, onReady: ready });
    el('target').addEventListener('input', ready);
    el('create').addEventListener('click', function () {
        el('create').disabled = true;
        post('create', { crop: el('crop').value, mandi: el('mandi').value, direction: el('direction').value, target_price: el('target').value })
            .then(function (j) {
                if (j.ok) { window.location.reload(); return; }
                status(j.error || T.err, 'danger');
                ready();
            });
    });
    el('checkNow').addEventListener('click', function () {
        var b = this; b.disabled = true;
        status(T.checking, 'info');
        post('check', {}).then(function (j) {
            if (!j.ok) { status(j.error || T.err, 'danger'); b.disabled = false; return; }
            window.sessionStorage && sessionStorage.setItem('alertCheck', JSON.stringify(j.data));
            window.location.reload();
        });
    });
    try {
        var saved = sessionStorage.getItem('alertCheck');
        if (saved) {
            sessionStorage.removeItem('alertCheck');
            var d = JSON.parse(saved), m = fmt(T.checkDone, d);
            if (d.unavailable > 0) { m += ' ' + fmt(T.checkUnavail, { n: d.unavailable }); }
            status(m, d.unavailable > 0 ? 'warning' : 'success');
        }
    } catch (e) { /* storage can be blocked: the page works without the message */ }
    document.querySelectorAll('.js-delete').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!window.confirm(T.confirmDelete)) { return; }
            post('delete', { id: b.getAttribute('data-id') }).then(function (j) {
                if (j.ok) { window.location.reload(); } else { status(j.error || T.err, 'danger'); }
            });
        });
    });
    document.querySelectorAll('.js-rearm').forEach(function (b) {
        b.addEventListener('click', function () {
            post('rearm', { id: b.getAttribute('data-id') }).then(function (j) {
                if (j.ok) { window.location.reload(); } else { status(j.error || T.err, 'danger'); }
            });
        });
    });
})();
</script>
