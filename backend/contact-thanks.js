/* Conram Contact Form Engine – Thank-you + Draft Cache (GDPR-safe)
   - Keeps a one-time draft of the contact form in sessionStorage on submit.
   - Rehydrates the form if the user returns (so they don't lose long messages).
   - Shows a friendly top-of-form error banner (if we detect an issue).
   - Still renders the thank-you personalization as before.
*/
(function () {
  const NS = 'ccfe_';
  const K = {
    tyName: NS + 'ty_name',
    tySubject: NS + 'ty_subject',
    tyRef: NS + 'ty_ref',
    draft: NS + 'draft',          // JSON payload of the form
    draftTs: NS + 'draft_ts'      // unix ms when draft was saved
  };
  const DRAFT_TTL_MS = 30 * 60 * 1000; // 30 minutes

  // ── Safe storage helpers ───────────────────────────────────────────────────
  const safe = {
    set(k, v) { try { sessionStorage.setItem(k, v); } catch (_) {} },
    get(k)    { try { return sessionStorage.getItem(k) || ''; } catch (_) { return ''; } },
    del(k)    { try { sessionStorage.removeItem(k); } catch (_) {} }
  };

  // ── Thank-you helpers (already in use) ─────────────────────────────────────
  function firstWord(s) { const t = String(s||'').trim(); return t ? t.split(/\s+/)[0].slice(0,60) : ''; }
  function makeRef() { return Math.random().toString(36).slice(2,8).toUpperCase(); }

  window.CCFE = window.CCFE || {};

  window.CCFE.setThankYouData = function ({ name, subject, ref }) {
    const first = firstWord(name);
    const subj  = String(subject || '').slice(0, 120);
    const rid   = (ref && String(ref).trim()) || makeRef();
    safe.set(K.tyName, first);
    safe.set(K.tySubject, subj);
    safe.set(K.tyRef, rid);
  };

  window.CCFE.renderThankYou = function () {
    const name = safe.get(K.tyName);
    const subj = safe.get(K.tySubject);
    const ref  = safe.get(K.tyRef);

    const boxEl  = document.getElementById('ccfe-ty-details');
    const nameEl = document.getElementById('ccfe-ty-name');
    const subEl  = document.getElementById('ccfe-ty-subject');
    const refEl  = document.getElementById('ccfe-ty-ref');

    if (boxEl && (name || subj || ref)) {
      if (nameEl) nameEl.textContent = name || '—';
      if (subEl)  subEl.textContent  = subj || '—';
      if (refEl)  refEl.textContent  = ref  || '—';
      boxEl.hidden = false;
    }

    // Clear one-time TY data
    [K.tyName, K.tySubject, K.tyRef].forEach(safe.del);
    // Also clear any lingering draft on success
    [K.draft, K.draftTs].forEach(safe.del);
  };

  // ── Draft Cache: capture on submit, rehydrate on load ──────────────────────
  function captureDraft(form) {
    const payload = {
      fullname: form.querySelector('[name="fullname"]')?.value || '',
      email:    form.querySelector('[name="email"]')?.value || '',
      subject:  form.querySelector('[name="subject"]')?.value || '',
      phone:    form.querySelector('[name="phone"]')?.value || '',
      message:  form.querySelector('[name="message"]')?.value || ''
    };
    safe.set(K.draft, JSON.stringify(payload));
    safe.set(K.draftTs, String(Date.now()));
  }

  function validDraftExists() {
    const ts = parseInt(safe.get(K.draftTs) || '0', 10);
    if (!ts) return false;
    return (Date.now() - ts) <= DRAFT_TTL_MS;
  }

  function rehydrateDraft(form) {
    if (!validDraftExists()) return false;
    const raw = safe.get(K.draft);
    if (!raw) return false;

    let data;
    try { data = JSON.parse(raw); } catch (_) { return false; }
    const assign = (sel, val) => { const el = form.querySelector(sel); if (el) el.value = val || ''; };

    assign('[name="fullname"]', data.fullname);
    assign('[name="email"]',    data.email);
    assign('[name="subject"]',  data.subject);
    assign('[name="phone"]',    data.phone);
    assign('[name="message"]',  data.message);

    return true;
  }

  function clearDraft() {
    [K.draft, K.draftTs].forEach(safe.del);
  }

  // ── Error banner on the contact page ───────────────────────────────────────
  function showErrorBanner(msg) {
    const box = document.getElementById('cf-error');
    if (!box) return;
    box.textContent = msg;
    box.hidden = false;
  }

  // Map simple codes to friendly messages (we can expand later)
  function messageFor(code) {
    switch (code) {
      case 'email':      return 'There is something wrong with your e-mail input, please try again.';
      case 'validation': return 'Some of the fields need attention. Please review and try again.';
      case 'too_fast':   return 'That was too fast. Please try again.';
      case 'rate_limited': return 'Too many attempts. Please wait a moment and try again.';
      default:           return 'We could not send your message. Please review and try again.';
    }
  }

  function getQueryParam(name) {
    const m = new RegExp('[?&]' + name + '=([^&#]*)').exec(location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
  }

  // ── Bindings ───────────────────────────────────────────────────────────────
  // 1) Capture draft BEFORE navigation (capture phase)
  document.addEventListener('submit', (ev) => {
    const f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;
    // Match this specific contact form by action or class
    const action = (f.getAttribute('action') || '').trim();
    if (!/^https:\/\/service\.conram\.it\/contact\.php$/i.test(action) && !f.classList.contains('cf')) return;

    captureDraft(f);

    // If the browser thinks email is invalid, prevent submit and show banner (keeps text in place)
    const emailEl = f.querySelector('[name="email"]');
    if (emailEl && !emailEl.checkValidity()) {
      ev.preventDefault();
      showErrorBanner(messageFor('email'));
      // focus email for quick fix
      try { emailEl.focus({ preventScroll: true }); } catch (_) {}
    }
  }, true);

  // 2) On contact form page: rehydrate the draft and show banner if ?err=...
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form.cf');
    if (form) {
      // If there is a recent draft and the form is empty or partially empty, restore it
      if (validDraftExists()) {
        const restored = rehydrateDraft(form);
        // Optional: clear after restoration to avoid stale data; keep it until a successful send if you prefer
        // clearDraft();
        if (restored && !getQueryParam('err')) {
          // No explicit error, but the user came back—show a gentle nudge?
          // (Commented by default)
          // showErrorBanner('Your unsent message was restored. Please review and send again.');
        }
      }

      // If server later redirects with ?err=code, show a friendly banner
      const err = getQueryParam('err');
      if (err) showErrorBanner(messageFor(err));
    }

    // Thank-you auto-render (unchanged)
    if (document.getElementById('ccfe-ty-root')) {
      window.CCFE.renderThankYou();
    }
  });
})();
