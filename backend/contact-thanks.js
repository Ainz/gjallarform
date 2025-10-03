/* Conram Contact Form Engine – Thank-you helper (ephemeral storage)
   Stores minimal, non-sensitive values in sessionStorage and reads them on /thank-you/.
   Also binds the submit listener to forms with class="cf" (no inline JS needed).
*/
(function () {
  const NS = 'ccfe_ty_';
  const K = { name: NS + 'name', subject: NS + 'subject', ref: NS + 'ref' };

  function safeSet(key, val) { try { sessionStorage.setItem(key, val); } catch (_) {} }
  function safeGet(key)      { try { return sessionStorage.getItem(key) || ''; } catch (_) { return ''; } }
  function safeDel(key)      { try { sessionStorage.removeItem(key); } catch (_) {} }

  function getFirstName(full) {
    const t = String(full || '').trim();
    return t ? t.split(/\s+/)[0].slice(0, 60) : '';
  }
  function makeRef() { return Math.random().toString(36).slice(2, 8).toUpperCase(); }

  // Public API (kept in case you ever want to call it manually)
  window.CCFE = window.CCFE || {};
  window.CCFE.setThankYouData = function ({ name, subject, ref }) {
    const first = getFirstName(name);
    const subj  = String(subject || '').slice(0, 120);
    const rid   = (ref && String(ref).trim()) || makeRef();
    safeSet(K.name, first);
    safeSet(K.subject, subj);
    safeSet(K.ref, rid);
  };

  window.CCFE.renderThankYou = function () {
    const name = safeGet(K.name);
    const subject = safeGet(K.subject);
    const ref = safeGet(K.ref);

    // Optional heading elements (only filled if present)
    const titleEl = document.getElementById('ccfe-ty-title');
    const leadEl  = document.getElementById('ccfe-ty-lead');

    const nameEl  = document.getElementById('ccfe-ty-name');
    const subjEl  = document.getElementById('ccfe-ty-subject');
    const refEl   = document.getElementById('ccfe-ty-ref');
    const boxEl   = document.getElementById('ccfe-ty-details');

    if (titleEl && name) titleEl.textContent = `Thanks, ${name}!`;
    if (leadEl) leadEl.textContent = 'Your message has been sent.';

    if (boxEl && (name || subject || ref)) {
      if (nameEl) nameEl.textContent = name || '—';
      if (subjEl) subjEl.textContent = subject || '—';
      if (refEl)  refEl.textContent  = ref || '—';
      boxEl.hidden = false;
    }

    // Clear immediately (one-time echo)
    [K.name, K.subject, K.ref].forEach(safeDel);
  };

  function bindFormListener() {
    const f = document.querySelector('form.cf');
    if (!f) return;

    f.addEventListener('submit', () => {
      try {
        // Match your actual field names
        const fullName = f.querySelector('[name="fullname"]')?.value || '';
        const subject  = f.querySelector('[name="subject"]')?.value || '';
        window.CCFE.setThankYouData({ name: fullName, subject });
      } catch (_) {}
    }, { passive: true });
  }

  document.addEventListener('DOMContentLoaded', () => {
    // On form pages, bind the submit hook
    bindFormListener();

    // On thank-you page, auto-render if marker exists
    if (document.getElementById('ccfe-ty-root')) {
      window.CCFE.renderThankYou();
    }
  });
})();
