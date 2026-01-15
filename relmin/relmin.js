/*!
 * Relmin UI helper (lean + field bubbles)
 * - Native validation bubbles only (no global banner)
 * - Email customValidity helper (friendly messages)
 * - Draft cache + TY page data via sessionStorage
 * - Rehydrate on ?err=…; reset on bfcache without ?err
 *
 * © 2025–present Conram.it. All rights reserved.
 * SPDX-License-Identifier: Proprietary
 */

(function () {
    // ───────────────────────────────────────────────────────────────────────────
    // Small helpers
    // ───────────────────────────────────────────────────────────────────────────
    function $(sel, root) { return (root || document).querySelector(sel); }
    function qsAll(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function firstWord(s) { var t = String(s || '').trim(); return t ? t.split(/\s+/)[0].slice(0, 60) : ''; }
    function makeRef() { return Math.random().toString(36).slice(2, 8).toUpperCase(); }
    function hasErrCode(code) { return new RegExp('[?&]err=' + code + '(?:&|$)').test(location.search); }

    // ───────────────────────────────────────────────────────────────────────────
    // Minimal CSS (Publii-safe <style>)
    // ───────────────────────────────────────────────────────────────────────────
var CSS = `
.relmin-wrapper{container-type:inline-size;container-name:relminwrapper}
.relmin-wrapper,.relmin{width:100%;max-width:100%;margin-left:auto;margin-right:auto;box-sizing:border-box}
.relmin{display:grid;grid-template-columns:repeat(2,minmax(280px,1fr));gap:1.25rem 2rem;margin:1rem 0 2rem}
.relmin__span-2{grid-column:1 / -1}
.relmin label{display:block;font-weight:700;margin-bottom:.4rem}
.relmin input,.relmin textarea{width:100%;padding:.8rem 1rem;border:1px solid #cfcfcf;border-radius:.6rem;font:inherit;line-height:1.4;background:#fff;box-sizing:border-box}
.relmin input:focus,.relmin textarea:focus{outline:2px solid rgba(0,108,255,.2);border-color:#6aa3ff}
.relmin__actions{margin-top:.5rem}
.relmin button{padding:.8rem 1.5rem;border:0;border-radius:.75rem;cursor:pointer}
.hp{position:absolute;left:-500vw;top:-500vh;height:0;width:0;overflow:hidden}
@media (max-width:768px){.relmin{grid-template-columns:1fr}}
@container relminwrapper (max-width:600px){.relmin{grid-template-columns:1fr}}
`;
    if (!document.getElementById('relmin-style')) {
        var st = document.createElement('style');
        st.id = 'relmin-style';
        st.textContent = CSS;
        document.head.appendChild(st);
    }

    // ───────────────────────────────────────────────────────────────────────────
    // sessionStorage keys + helpers
    // ───────────────────────────────────────────────────────────────────────────
    var NS = 'ctls_';
    var K = { tyName: NS + 'ty_name', tySubject: NS + 'ty_subject', tyRef: NS + 'ty_ref', draft: NS + 'draft', draftTs: NS + 'draft_ts' };
    var TTL = 30 * 60 * 1000;

    function Sset(k, v) { try { sessionStorage.setItem(k, v); } catch (_) { } }
    function Sget(k) { try { return sessionStorage.getItem(k) || ''; } catch (_) { return '' } }
    function Sdel(k) { try { sessionStorage.removeItem(k); } catch (_) { } }

    // ───────────────────────────────────────────────────────────────────────────
    // TY data API
    // ───────────────────────────────────────────────────────────────────────────
    window.Relmin = window.Relmin || {};
    window.Relmin.setThankYou = function (payload) {
        Sset(K.tyName, firstWord(payload.name || ''));
        Sset(K.tySubject, String(payload.subject || '').slice(0, 120));
        Sset(K.tyRef, (payload.ref && String(payload.ref).trim()) || makeRef());
    };
    window.Relmin.renderThankYou = function () {
        var box = $('#ccfe-ty-details'), n = $('#ccfe-ty-name'), s = $('#ccfe-ty-subject'), r = $('#ccfe-ty-ref');
        var name = Sget(K.tyName), subj = Sget(K.tySubject), ref = Sget(K.tyRef);
        if (box && (name || subj || ref)) {
            if (n) n.textContent = name || '—'; if (s) s.textContent = subj || '—'; if (r) r.textContent = ref || '—';
            box.hidden = false;
        }
        [K.tyName, K.tySubject, K.tyRef, K.draft, K.draftTs].forEach(Sdel);
    };

    // ───────────────────────────────────────────────────────────────────────────
    // Draft capture / rehydrate (only on ?err=…)
    // ───────────────────────────────────────────────────────────────────────────
    function snapshotForm(form) {
        var get = function (n) { var el = form.querySelector('[name="' + n + '"]'); return el ? el.value : '' };
        var p = { fullname: get('fullname'), email: get('email'), subject: get('subject'), message: get('message') };
        Sset(K.draft, JSON.stringify(p)); Sset(K.draftTs, String(Date.now()));
    }
    function validDraft() { var ts = parseInt(Sget(K.draftTs) || '0', 10); return ts && (Date.now() - ts) <= TTL; }
    function rehydrate(form) {
        if (!validDraft()) return; var raw = Sget(K.draft); if (!raw) return;
        try {
            var d = JSON.parse(raw), set = function (n, v) { var el = form.querySelector('[name="' + n + '"]'); if (el) el.value = v || ''; };
            set('fullname', d.fullname); set('email', d.email); set('subject', d.subject); set('message', d.message);
        } catch (_) { }
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Field-level helpers (native bubbles via setCustomValidity)
    // ───────────────────────────────────────────────────────────────────────────
    function attachEmailAsciiGuard(input) {
        if (!input) return;
        function checkEmailAscii() {
            var v = input.value || '';
            if (!v) { input.setCustomValidity(''); return; } // required attribute handles empties
            if (/[^\x00-\x7F]/.test(v)) { input.setCustomValidity('Use standard ASCII email (no accented characters).'); return; }
            // Let the browser’s type=email handle the rest
            input.setCustomValidity('');
        }
        input.addEventListener('input', checkEmailAscii);
        input.addEventListener('blur', checkEmailAscii);

        // If server bounced with email_* codes, surface a general hint
        if (hasErrCode('email_ascii_only') || hasErrCode('email_invalid') || hasErrCode('email_invalid_at')) {
            checkEmailAscii();
            setTimeout(function () { input.reportValidity && input.reportValidity(); input.focus({ preventScroll: false }); }, 0);
        }
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Submit: snapshot draft, rely on native validity, stash TY data when OK
    // ───────────────────────────────────────────────────────────────────────────
    document.addEventListener('submit', function (ev) {
        var f = ev.target;
        if (!(f instanceof HTMLFormElement)) return;

        snapshotForm(f);

        if (!f.checkValidity()) {
            ev.preventDefault();
            // Focus the first invalid control, trigger native bubble
            var bad = f.querySelector(':invalid');
            if (bad && typeof bad.reportValidity === 'function') {
                bad.reportValidity();
                bad.focus({ preventScroll: true });
            } else if (typeof f.reportValidity === 'function') {
                f.reportValidity();
            }
            return;
        }

        var fullname = (f.querySelector('[name="fullname"]') || {}).value || '';
        var subject = (f.querySelector('[name="subject"]') || {}).value || '';
        window.Relmin.setThankYou({ name: fullname, subject });
    }, true);

    // ───────────────────────────────────────────────────────────────────────────
    // On load + bfcache handling
    // ───────────────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var form = $('form.relmin');
        var hasErr = /[?&]err=/.test(location.search);

        if (form) {
            form.setAttribute('autocomplete', 'off');

            // ►► Arm time-trap: set ms timestamp for the server check
            var tsEl = form.querySelector('[name="render_ts"]') || document.querySelector('[name="render_ts"]');
            if (tsEl) tsEl.value = String(Date.now());

            // Attach field helpers
            attachEmailAsciiGuard($('[name="email"]', form));

            if (hasErr) rehydrate(form); else[K.draft, K.draftTs].forEach(Sdel);
        }

        if (document.getElementById('ccfe-ty-root')) {
            window.Relmin.renderThankYou();
        }
    });

    window.addEventListener('pageshow', function (e) {
        var form = $('form.relmin'); var hasErr = /[?&]err=/.test(location.search);
        if (form && e.persisted && !hasErr) { try { form.reset(); } catch (_) { } [K.draft, K.draftTs].forEach(Sdel); }
    });
})();
