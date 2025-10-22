/*!
 * Contactulus — lightweight contact form UI helpers
 * © 2025–present Conram.it. All rights reserved.
 * SPDX-License-Identifier: Proprietary
 * https://www.conram.it
 */
"use strict";


/*! Contactulus UI helper
    - Injects CSS (so Publii doesn't strip <link>)
    - Draft cache (sessionStorage) to avoid losing long messages
    - Friendly validation banner + native HTML5 tooltips
    - Thank-you page personalization
    - F5/back-forward cache handling:
        * Only rehydrate when redirected back with ?err=...
        * On plain reload/back without ?err=, start clean
*/
(function () {
  // ───────────────────────────────────────────────────────────────────────────
  // 1) CSS injector (no <link> tag; safe with Publii sanitization)
  // ───────────────────────────────────────────────────────────────────────────
var CSS = `
/* Wrapper: let the form breathe inside Publii's content column */
.contactulus-wrapper,
.cf {
  width: 100%;
  max-width: 72rem;           /* ~1152px cap; tune if you like */
  margin-left: auto;
  margin-right: auto;
}

/* Grid: 2 columns when space allows, else 1 column.
   Each column must be at least 360px so placeholders don't get cramped. */
.cf {
  display: grid;
  grid-template-columns: repeat(2, minmax(360px, 1fr));
  gap: 1.25rem 2rem;          /* row gap / column gap */
  margin: 1rem 0 2rem;
  box-sizing: border-box;
}

.cf__span-2 { grid-column: 1 / -1; }

.cf label {
  display: block;
  font-weight: 700;
  margin-bottom: .4rem;
}

.cf input,
.cf textarea {
  width: 100%;
  padding: .8rem 1rem;
  border: 1px solid #cfcfcf;
  border-radius: .6rem;
  font: inherit;
  line-height: 1.4;
  background: #fff;
  box-sizing: border-box;
}

.cf input:focus,
.cf textarea:focus {
  outline: 2px solid rgba(0,108,255,.2);
  border-color: #6aa3ff;
}

.cf__actions { margin-top: .5rem; }
.cf button   { padding: .8rem 1.5rem; border: 0; border-radius: .75rem; cursor: pointer; }

/* Honeypot + error banner */
.hp { position: absolute; left: -500vw; top: -500vh; height: 0; width: 0; overflow: hidden; }
.cf-error { background: #ffe9e9; color: #7a1b1b; padding: .75rem 1rem; border-radius: .55rem; margin: .25rem 0 .75rem; }

/* Break to 1 column sooner on mid-width layouts */
@media (max-width: 900px) {
  .cf { grid-template-columns: 1fr; }
}
`;

  if (!document.getElementById('contactulus-style')) {
    var s = document.createElement('style');
    s.id = 'contactulus-style';
    s.textContent = CSS;
    document.head.appendChild(s);
  }

  // ───────────────────────────────────────────────────────────────────────────
  // 2) Storage helpers & keys (namespaced; session only)
  // ───────────────────────────────────────────────────────────────────────────
  var NS = 'ctls_';
  var K = {
    tyName:   NS + 'ty_name',     // first name for TY page
    tySubject:NS + 'ty_subject',  // subject for TY page
    tyRef:    NS + 'ty_ref',      // short ref for TY page
    draft:    NS + 'draft',       // JSON snapshot of form
    draftTs:  NS + 'draft_ts'     // when snapshot was taken
  };
  var TTL = 30 * 60 * 1000; // 30 minutes

  function Sset(k, v) { try { sessionStorage.setItem(k, v); } catch (_) {} }
  function Sget(k)    { try { return sessionStorage.getItem(k) || ''; } catch (_) { return ''; } }
  function Sdel(k)    { try { sessionStorage.removeItem(k); } catch (_) {} }

  // ───────────────────────────────────────────────────────────────────────────
  // 3) Thank-you helpers (first name + ref; then clear)
  // ───────────────────────────────────────────────────────────────────────────
  function firstWord(s) { var t = String(s || '').trim(); return t ? t.split(/\s+/)[0].slice(0, 60) : ''; }
  function makeRef()    { return Math.random().toString(36).slice(2, 8).toUpperCase(); }

  window.Contactulus = window.Contactulus || {};

  // Save minimal, non-sensitive info for rendering on /thank-you.html
  window.Contactulus.setThankYou = function (payload) {
    Sset(K.tyName,    firstWord(payload.name || ''));
    Sset(K.tySubject, String(payload.subject || '').slice(0, 120));
    Sset(K.tyRef,     (payload.ref && String(payload.ref).trim()) || makeRef());
  };

  // Render and immediately clear TY data (one-time use)
  window.Contactulus.renderThankYou = function () {
    var name = Sget(K.tyName), subj = Sget(K.tySubject), ref = Sget(K.tyRef);
    var box  = document.getElementById('ccfe-ty-details');
    if (box && (name || subj || ref)) {
      var n = document.getElementById('ccfe-ty-name');
      var s = document.getElementById('ccfe-ty-subject');
      var r = document.getElementById('ccfe-ty-ref');
      if (n) n.textContent = name || '—';
      if (s) s.textContent = subj || '—';
      if (r) r.textContent = ref  || '—';
      box.hidden = false;
    }
    [K.tyName, K.tySubject, K.tyRef, K.draft, K.draftTs].forEach(Sdel);
  };

  // ───────────────────────────────────────────────────────────────────────────
  // 4) Draft cache (capture → maybe restore later)
  // ───────────────────────────────────────────────────────────────────────────
  function captureDraft(form) {
    var p = {
      fullname: form.querySelector('[name="fullname"]')?.value || '',
      email:    form.querySelector('[name="email"]')?.value    || '',
      subject:  form.querySelector('[name="subject"]')?.value  || '',
      phone:    form.querySelector('[name="phone"]')?.value    || '',
      message:  form.querySelector('[name="message"]')?.value  || ''
    };
    Sset(K.draft, JSON.stringify(p));
    Sset(K.draftTs, String(Date.now()));
  }

  function validDraft() {
    var ts = parseInt(Sget(K.draftTs) || '0', 10);
    return ts && (Date.now() - ts) <= TTL;
  }

  function rehydrate(form) {
    if (!validDraft()) return;
    var raw = Sget(K.draft);
    if (!raw) return;
    try {
      var d = JSON.parse(raw);
      var set = function (sel, val) { var el = form.querySelector(sel); if (el) el.value = val || ''; };
      set('[name="fullname"]', d.fullname);
      set('[name="email"]',    d.email);
      set('[name="subject"]',  d.subject);
      set('[name="phone"]',    d.phone);
      set('[name="message"]',  d.message);
    } catch (_) {}
  }

  // ───────────────────────────────────────────────────────────────────────────
  // 5) Validation summary banner (complements native tooltips)
  // ───────────────────────────────────────────────────────────────────────────
  function showBanner(msg) {
    var el = document.getElementById('cf-error'); if (!el) return;
    el.textContent = msg;
    el.hidden = false;
  }

  function summarizeInvalid(form) {
    var fields = Array.prototype.slice.call(form.querySelectorAll('input,textarea,select'));
    var bad = fields.filter(function (el) { return !el.checkValidity(); });
    if (!bad.length) return null;

    function label(el) { return el.getAttribute('aria-label') || el.name || 'This field'; }
    function msg(el) {
      var v = el.validity, L = label(el);
      if (v.valueMissing)    return L + ': required.';
      if (v.typeMismatch)    return (el.type === 'email') ? L + ': enter a valid email.' : L + ': invalid value.';
      if (v.patternMismatch) return L + ': invalid format.';
      if (v.tooLong)         return L + ': too long (max ' + el.maxLength + ').';
      if (v.tooShort)        return L + ': too short (min ' + el.minLength + ').';
      return L + ': check this value.';
    }

    var lines = bad.slice(0, 2).map(msg);
    if (bad.length > 2) lines.push('(+ ' + (bad.length - 2) + ' more)');
    return lines.join(' ');
  }

  // ───────────────────────────────────────────────────────────────────────────
  // 6) Submit handling (capture draft → validate → maybe store TY data)
  // ───────────────────────────────────────────────────────────────────────────
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;

    // Always capture a draft before navigation
    captureDraft(f);

    // Let HTML5 validation run; if invalid, stop and show a friendly summary
    if (!f.checkValidity()) {
      ev.preventDefault();
      showBanner(summarizeInvalid(f) || 'Some fields need attention.');
      f.reportValidity(); // triggers native tooltips
      try { f.querySelector(':invalid')?.focus({ preventScroll: true }); } catch (_) {}
      return;
    }

    // Valid → stash minimal data for thank-you page
    var fullname = f.querySelector('[name="fullname"]')?.value || '';
    var subject  = f.querySelector('[name="subject"]')?.value  || '';
    window.Contactulus.setThankYou({ name: fullname, subject });
    // Allow normal submit to continue (PRG handled server-side)
  }, true); // capture phase: runs before navigation

  // ───────────────────────────────────────────────────────────────────────────
  // 7) On load + F5/back-forward handling
  //     - Only rehydrate when redirected back with ?err=...
  //     - Otherwise clear stale drafts and (if coming from bfcache) reset form
  // ───────────────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    var form   = document.querySelector('form.cf');
    var hasErr = /[?&]err=/.test(location.search);

    if (form) {
      // Reduce surprise autofill on reload
      form.setAttribute('autocomplete', 'off');

      if (hasErr) {
        // Server sent us back with an error → restore the user's draft
        rehydrate(form);
      } else {
        // Fresh visit/reload → start clean
        [K.draft, K.draftTs].forEach(Sdel);
      }
    }

    // Auto render TY if that page is loaded
    if (document.getElementById('ccfe-ty-root')) {
      window.Contactulus.renderThankYou();
    }
  });

  // Handle browser back/forward cache (page shown from cache after navigation)
  window.addEventListener('pageshow', function (e) {
    var form   = document.querySelector('form.cf');
    var hasErr = /[?&]err=/.test(location.search);

    // If the page is restored from bfcache AND we did not come back with ?err=,
    // reset form & clear draft so F5/back appears empty and consistent.
    if (form && e.persisted && !hasErr) {
      try { form.reset(); } catch (_) {}
      [K.draft, K.draftTs].forEach(Sdel);
    }
  });
})();
