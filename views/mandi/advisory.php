<?php
// Ask Mandi Setu (F6). Variables from AdvisoryController::index(): $crops, $maxQuestion, $uiLang.
$mandiActive = 'ask';
$t = function ($k) { return __($k); };
?>
<div class="container py-5">
    <div class="mb-3">
        <h2 class="fw-bold" style="color: var(--primary-green);"><i class="fa-solid fa-comments"></i> <?= e(__('ad_title')) ?></h2>
        <p class="text-muted mb-3"><?= e(__('ad_desc')) ?></p>
    </div>
    <?php require __DIR__ . '/_nav.php'; ?>

    <div id="status" class="alert d-none" role="alert"></div>

    <div class="card shadow-sm border-0 mb-4" style="border-top: 3px solid var(--primary-orange);">
        <div class="card-body p-4">
            <div class="mb-3">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="mode" id="modeOne" value="one" checked>
                    <label class="form-check-label" for="modeOne"><?= e(__('ad_mode_one')) ?></label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="mode" id="modeCompare" value="compare">
                    <label class="form-check-label" for="modeCompare"><?= e(__('ad_mode_compare')) ?></label>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="crop"><?= e(__('mp_crop')) ?></label>
                    <select class="form-select" id="crop">
                        <option value=""><?= e(__('mp_choose_crop')) ?></option>
                        <?php foreach ($crops as $c): ?>
                            <option value="<?= e($c) ?>"><?= e(\App\Helpers\MandiText::cropLabel($c, $t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5" id="oneBox">
                    <label class="form-label fw-bold" for="mandi"><?= e(__('mp_mandi')) ?></label>
                    <select class="form-select" id="mandi" disabled><option value=""><?= e(__('mp_choose_crop_first')) ?></option></select>
                </div>
                <div class="col-md-5 d-none" id="manyBox">
                    <label class="form-label fw-bold" for="mandis"><?= e(__('ad_pick_mandis')) ?></label>
                    <select class="form-select" id="mandis" multiple size="5" disabled></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="language"><?= e(__('ad_question')) ?> (EN / हिं / ਪੰ)</label>
                    <select class="form-select" id="language">
                        <option value="en" <?= e($uiLang === 'en' ? 'selected' : '') ?>>English</option>
                        <option value="hi" <?= e($uiLang === 'hi' ? 'selected' : '') ?>>हिंदी</option>
                        <option value="pa" <?= e($uiLang === 'pa' ? 'selected' : '') ?>>ਪੰਜਾਬੀ</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label fw-bold" for="question"><?= e(__('ad_question')) ?></label>
                <textarea class="form-control" id="question" rows="3" maxlength="<?= e((int) $maxQuestion) ?>" placeholder="<?= e(__('ad_placeholder')) ?>"></textarea>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-primary-custom fw-bold" id="ask"><i class="fa-solid fa-paper-plane"></i> <?= e(__('ad_ask')) ?></button>
                <button type="button" class="btn btn-outline-secondary d-none" id="speak"><i class="fa-solid fa-microphone"></i> <?= e(__('ad_speak')) ?></button>
            </div>
            <p class="text-muted small mt-3 mb-0 d-none" id="voiceNote"><?= e(__('ad_voice_note')) ?></p>
        </div>
    </div>

    <div class="card shadow-sm border-0 d-none" id="answerCard">
        <div class="card-body p-4">
            <h5 class="fw-bold"><?= e(__('ad_answer')) ?></h5>
            <p id="answer" class="mb-2" style="white-space: pre-line;"></p>
            <p class="text-muted small mb-2" id="source"></p>
            <button type="button" class="btn btn-outline-success btn-sm" id="listen"><i class="fa-solid fa-volume-high"></i> <?= e(__('ad_listen_answer')) ?></button>
            <audio id="player" class="d-block mt-3 w-100 d-none" controls></audio>
            <p class="text-muted small mt-3 mb-0"><?= e(__('ad_disclaimer')) ?></p>
        </div>
    </div>
</div>
<script src="<?= e(BASE_URL) ?>/assets/js/mandi-pick.js?v=1"></script>
<script>
(function () {
    var BASE = <?= json_encode(BASE_URL . '/index.php?url=', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var MAXQ = <?= e((int) $maxQuestion) ?>;
    var T = <?= json_encode([
        'chooseMandi' => __('mp_choose_mandi'), 'chooseCropFirst' => __('mp_choose_crop_first'),
        'loadingMandis' => __('mp_loading_mandis'), 'errMandis' => __('mp_err_mandis'),
        'thinking' => __('ad_thinking'), 'listening' => __('ad_listening'), 'loadingAudio' => __('ad_loading_audio'),
        'sourceGemini' => __('ad_source_gemini'), 'sourceFallback' => __('ad_source_fallback'),
        'errEmpty' => __('ad_err_empty'), 'errLong' => __('ad_err_long'), 'errPick' => __('ad_err_pick'), 'errPickMany' => __('ad_err_pick_many'),
        'errVoice' => __('ad_err_voice'), 'unsupported' => __('ad_voice_unsupported'), 'micDenied' => __('ad_mic_denied'),
        'needsNames' => __('ad_voice_needs_names'), 'errRead' => __('mp_err_read'), 'err' => __('al_err_generic'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var SPEECH_LANG = { en: 'en-IN', hi: 'hi-IN', pa: 'pa-IN' };
    function el(id) { return document.getElementById(id); }
    function fmt(text, vars) { return String(text).replace(/\{(\w+)\}/g, function (m, k) { return vars[k] === undefined ? m : vars[k]; }); }
    function status(msg, type) {
        var s = el('status');
        if (!msg) { s.className = 'alert d-none'; s.textContent = ''; return; }
        s.className = 'alert alert-' + type;
        s.textContent = msg;
    }
    function isCompare() { return el('modeCompare').checked; }
    function selectedMandis() {
        return Array.prototype.filter.call(el('mandis').options, function (o) { return o.selected && o.value; }).map(function (o) { return o.value; });
    }
    function form(extra) {
        var b = new URLSearchParams();
        b.append('question', el('question').value);
        b.append('language', el('language').value);
        b.append('crop', el('crop').value);
        Object.keys(extra || {}).forEach(function (k) { b.append(k, extra[k]); });
        return b;
    }
    function post(action, body) {
        return fetch(BASE + 'advisory/' + action, { method: 'POST', credentials: 'same-origin', body: body });
    }
    function validate() {
        var q = el('question').value.trim();
        if (!q) { status(T.errEmpty, 'warning'); return false; }
        if (q.length > MAXQ) { status(fmt(T.errLong, { n: MAXQ }), 'warning'); return false; }
        if (isCompare()) {
            var n = selectedMandis().length;
            if (!el('crop').value || n < 2 || n > 5) { status(T.errPickMany, 'warning'); return false; }
        } else if (!el('crop').value || !el('mandi').value) { status(T.errPick, 'warning'); return false; }
        return true;
    }

    // crop -> mandi pickers (one list for "one mandi", a multi-select for "compare")
    var onePick = { crop: el('crop'), mandi: el('mandi'), url: BASE + 'mandi/mandis&crop=', texts: T, onStatus: status };
    MandiPick.bind(onePick);
    el('crop').addEventListener('change', function () {
        // also fill the multi-select for compare mode
        var crop = this.value, many = el('mandis');
        many.innerHTML = ''; many.disabled = true;
        if (!crop) { return; }
        fetch(BASE + 'mandi/mandis&crop=' + encodeURIComponent(crop), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok || el('crop').value !== crop) { return; }
                j.mandis.forEach(function (n) { many.appendChild(new Option(n, n)); });
                many.disabled = false;
            }).catch(function () { /* the single-mandi list already reported the error */ });
    });
    function setMode() {
        el('oneBox').classList.toggle('d-none', isCompare());
        el('manyBox').classList.toggle('d-none', !isCompare());
    }
    el('modeOne').addEventListener('change', setMode);
    el('modeCompare').addEventListener('change', setMode);

    // Ask (typed or spoken question)
    el('ask').addEventListener('click', function () {
        status('', '');
        if (!validate()) { return; }
        var b = this; b.disabled = true;
        status(T.thinking, 'info');
        var extra = isCompare() ? { mode: 'compare', mandis: selectedMandis().join(',') } : { mode: 'one', mandi: el('mandi').value };
        post('ask', form(extra)).then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, error: T.errRead }; })
            .then(function (j) {
                b.disabled = false;
                if (!j.ok) { status(j.error || T.err, 'danger'); return; }
                status('', '');
                el('answer').textContent = j.data.answer;
                el('source').textContent = j.data.source === 'gemini' ? T.sourceGemini : T.sourceFallback;
                el('player').classList.add('d-none');
                el('player').removeAttribute('src');
                el('answerCard').classList.remove('d-none');
            });
    });

    // Spoken answer (MP3 from the service, played in the page)
    el('listen').addEventListener('click', function () {
        var b = this; b.disabled = true;
        status(T.loadingAudio, 'info');
        var extra = {};
        if (!isCompare() && el('mandi').value) { extra.mandi = el('mandi').value; }
        post('speak', form(extra)).then(function (r) {
            var type = r.headers.get('Content-Type') || '';
            if (r.ok && type.indexOf('audio/') === 0) { return r.blob().then(function (blob) { return { ok: true, blob: blob }; }); }
            return r.json().catch(function () { return { ok: false, error: T.errVoice }; });
        }).catch(function () { return { ok: false, error: T.errVoice }; })
        .then(function (j) {
            b.disabled = false;
            if (!j.ok) { status(j.error || T.errVoice, 'warning'); return; }
            status('', '');
            var p = el('player');
            if (p.src) { URL.revokeObjectURL(p.src); }
            p.src = URL.createObjectURL(j.blob);
            p.classList.remove('d-none');
            p.play().catch(function () { /* the browser may need a tap on the player */ });
        });
    });

    // Speak the question (browser speech recognition: audio goes to Google, see the note on the page)
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SR) {
        el('speak').classList.remove('d-none');
        el('voiceNote').classList.remove('d-none');
        var rec = null;
        el('speak').addEventListener('click', function () {
            if (rec) { rec.stop(); return; }
            rec = new SR();
            rec.lang = SPEECH_LANG[el('language').value] || 'en-IN';
            rec.interimResults = false;
            rec.maxAlternatives = 1;
            status(T.listening, 'info');
            rec.onresult = function (ev) {
                var text = ev.results[0][0].transcript || '';
                el('question').value = text.slice(0, MAXQ);
            };
            rec.onerror = function (ev) {
                status(ev.error === 'not-allowed' || ev.error === 'service-not-allowed' ? T.micDenied : T.unsupported, 'warning');
            };
            rec.onend = function () { rec = null; if (el('status').textContent === T.listening) { status('', ''); } };
            try { rec.start(); } catch (e) { rec = null; status(T.unsupported, 'warning'); }
        });
    }
})();
</script>
