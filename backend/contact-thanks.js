/* Conram Contact Form Engine – UI helpers
   - Draft cache (sessionStorage): prevents data loss on errors/navigation
   - Universal validation: friendly banner + native tooltips
   - Thank-you page personalization
*/
(function () {
  const NS = 'ccfe_';
  const K = {
    tyName: NS + 'ty_name',
    tySubject: NS + 'ty_subject',
    tyRef: NS + 'ty_ref',
    draft: NS + 'draft',
    draftTs: NS + 'draft_ts'
  };
  const DRAFT_TTL_MS = 30 * 60 * 1000; // 30 min

  // ── storage helpers ──
  const S = {
    set(k,v){ try{ sessionStorage.setItem(k,v);}catch(_){} },
    get(k){ try{ return sessionStorage.getItem(k)||'';}catch(_){ return '';} },
    del(k){ try{ sessionStorage.removeItem(k);}catch(_){} }
  };

  // ── thank-you helpers ──
  function firstWord(s){ const t=String(s||'').trim(); return t?t.split(/\s+/)[0].slice(0,60):''; }
  function makeRef(){ return Math.random().toString(36).slice(2,8).toUpperCase(); }

  window.CCFE = window.CCFE || {};
  window.CCFE.setThankYouData = function({name,subject,ref}){
    S.set(K.tyName, firstWord(name));
    S.set(K.tySubject, String(subject||'').slice(0,120));
    S.set(K.tyRef, (ref && String(ref).trim()) || makeRef());
  };
  window.CCFE.renderThankYou = function(){
    const name=S.get(K.tyName), subj=S.get(K.tySubject), ref=S.get(K.tyRef);
    const box=document.getElementById('ccfe-ty-details');
    if (box && (name||subj||ref)){
      const n=document.getElementById('ccfe-ty-name');
      const s=document.getElementById('ccfe-ty-subject');
      const r=document.getElementById('ccfe-ty-ref');
      if(n) n.textContent=name||'—';
      if(s) s.textContent=subj||'—';
      if(r) r.textContent=ref||'—';
      box.hidden=false;
    }
    [K.tyName,K.tySubject,K.tyRef,K.draft,K.draftTs].forEach(S.del); // clear on success
  };

  // ── draft cache ──
  function captureDraft(form){
    const payload={
      fullname: form.querySelector('[name="fullname"]')?.value||'',
      email:    form.querySelector('[name="email"]')?.value||'',
      subject:  form.querySelector('[name="subject"]')?.value||'',
      phone:    form.querySelector('[name="phone"]')?.value||'',
      message:  form.querySelector('[name="message"]')?.value||''
    };
    S.set(K.draft, JSON.stringify(payload));
    S.set(K.draftTs, String(Date.now()));
  }
  function validDraft(){ const ts=parseInt(S.get(K.draftTs)||'0',10); return ts && (Date.now()-ts)<=DRAFT_TTL_MS; }
  function rehydrateDraft(form){
    if(!validDraft()) return false;
    let data; try{ data=JSON.parse(S.get(K.draft)||''); }catch(_){ return false; }
    if(!data) return false;
    const set=(sel,val)=>{ const el=form.querySelector(sel); if(el) el.value=val||''; };
    set('[name="fullname"]',data.fullname);
    set('[name="email"]',   data.email);
    set('[name="subject"]', data.subject);
    set('[name="phone"]',   data.phone);
    set('[name="message"]', data.message);
    return true;
  }

  // ── banner ──
  function banner(){ return document.getElementById('cf-error'); }
  function showBanner(text){
    const b=banner(); if(!b) return;
    b.textContent=text; b.hidden=false;
  }
  function clearBanner(){ const b=banner(); if(b) b.hidden=true; }

  // human messages per validity state
  function msgForField(el){
    const label = el.closest('label')?.querySelector('span, strong')?.textContent?.trim()
               || el.getAttribute('aria-label')
               || el.name || 'This field';
    const v = el.validity;
    if (v.valueMissing)      return `${label}: this field is required.`;
    if (v.typeMismatch) {
      if (el.type === 'email') return `${label}: please enter a valid email address.`;
      return `${label}: invalid value.`;
    }
    if (v.patternMismatch)   return `${label}: value format is not accepted.`;
    if (v.tooShort)          return `${label}: too short (min ${el.minLength}).`;
    if (v.tooLong)           return `${label}: too long (max ${el.maxLength}).`;
    if (v.rangeUnderflow)    return `${label}: value is too small.`;
    if (v.rangeOverflow)     return `${label}: value is too large.`;
    if (v.stepMismatch)      return `${label}: invalid step value.`;
    return `${label}: please check this value.`;
  }

  function summarizeInvalid(form){
    const fields = Array.from(form.querySelectorAll('input, textarea, select'));
    const invalid = fields.filter(el => !el.checkValidity());
    if (invalid.length === 0) return null;
    // Build a short, friendly summary (first 2 errors; native tooltips will show details)
    const lines = invalid.slice(0,2).map(msgForField);
    const more  = invalid.length > 2 ? ` (+${invalid.length-2} more)` : '';
    return lines.join(' ') + more;
  }

  // ── submit handling ──
  document.addEventListener('submit', (ev) => {
    const f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;

    // Match our contact form by action or class
    const action=(f.getAttribute('action')||'').trim();
    if (!/^https:\/\/service\.conram\.it\/contact\.php$/i.test(action) && !f.classList.contains('cf')) return;

    // Always capture draft first
    captureDraft(f);

    // If invalid, stop submit, show banner + native tooltips, focus first invalid
    if (!f.checkValidity()) {
      ev.preventDefault();
      const summary = summarizeInvalid(f) || 'Some fields need attention. Please review and try again.';
      clearBanner(); showBanner(summary);
      // Trigger native messages and focus the first invalid element
      f.reportValidity();
      const firstInvalid = f.querySelector(':invalid');
      if (firstInvalid) { try { firstInvalid.focus({preventScroll:true}); } catch(_){} }
      return;
    }

    // If valid, also stash minimal data for thank-you personalization
    const fullname = f.querySelector('[name="fullname"]')?.value || '';
    const subject  = f.querySelector('[name="subject"]')?.value || '';
    if (window.CCFE && typeof window.CCFE.setThankYouData === 'function') {
      window.CCFE.setThankYouData({ name: fullname, subject });
    }
    // Let the browser submit normally
  }, true); // capture = runs before navigation

  // ── on load ──
  document.addEventListener('DOMContentLoaded', () => {
    // Contact page: rehydrate draft (if any)
    const form=document.querySelector('form.cf');
    if (form) { rehydrateDraft(form); /* keep draft until success */ }

    // Thank-you page: render and clear
    if (document.getElementById('ccfe-ty-root')) {
      window.CCFE.renderThankYou();
    }
  });
})();
