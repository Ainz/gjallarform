/*!
 * Gjallarform UI helper (lean + field bubbles)
 * - Native validation bubbles only (no global banner)
 * - Email customValidity helper (friendly messages)
 * - Draft cache + TY page data via sessionStorage
 * - Rehydrate on ?err=…; reset on bfcache without ?err
 *
 * © 2025–present Conram.it. All rights reserved.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

(function () {
    // ---------------------------------------------------------------------------
    // Minimal CSS (Publii-safe <style>)
    // ---------------------------------------------------------------------------
var CSS = `
.gjallarform-wrapper{container-type:inline-size;container-name:gjallarformwrapper}
.gjallarform-wrapper,.gjallarform{width:100%;max-width:100%;margin-left:auto;margin-right:auto;box-sizing:border-box}
.gjallarform{display:grid;grid-template-columns:repeat(2,minmax(280px,1fr));gap:1.25rem 2rem;margin:1rem 0 2rem}
.gjallarform__span-2{grid-column:1 / -1}
.gjallarform label{display:block;font-weight:700;margin-bottom:.4rem}
.gjallarform input,.gjallarform textarea{width:100%;padding:.8rem 1rem;border:1px solid #cfcfcf;border-radius:.6rem;font:inherit;line-height:1.4;background:#fff;box-sizing:border-box}
.gjallarform input:focus,.gjallarform textarea:focus{outline:2px solid rgba(0,108,255,.2);border-color:#6aa3ff}
.gjallarform__actions{margin-top:.5rem}
.gjallarform button{padding:.8rem 1.5rem;border:0;border-radius:.75rem;cursor:pointer}
.hp{position:absolute;left:-500vw;top:-500vh;height:0;width:0;overflow:hidden}
@media (max-width:768px){.gjallarform{grid-template-columns:1fr}}
@container gjallarformwrapper (max-width:600px){.gjallarform{grid-template-columns:1fr}}
`;
    if (!document.getElementById('gjallarform-style')) {
        var st = document.createElement('style');
        st.id = 'gjallarform-style';
        st.textContent = CSS;
        document.head.appendChild(st);
    }

    // ---------------------------------------------------------------------------
    // Math challenge questions — any edit here must be mirrored in the PHP backend
    // ---------------------------------------------------------------------------
    var mathQuestions = [
        { question: 'What is 5 + 3?', answer: '8' },
        { question: 'What is 10 - 4?', answer: '6' },
        { question: 'What is 6 × 2?', answer: '12' },
        { question: 'What is 15 ÷ 3?', answer: '5' },
        { question: 'What is 7 + 8?', answer: '15' },
        { question: 'What is 20 - 11?', answer: '9' },
        { question: 'What is 4 × 3?', answer: '12' }
    ];

    // ---------------------------------------------------------------------------
    // No changes are normally needed below this line
    // ---------------------------------------------------------------------------

    // ---------------------------------------------------------------------------
    // Small helpers
    // ---------------------------------------------------------------------------
    /**
     * jQuery-like selector helper - returns first matching element.
     * @param {string} sel - CSS selector to query
     * @param {Element|Document} [root=document] - Root element to search within
     * @returns {Element|null} First matching element or null if not found
     */
    function $(sel, root) { return (root || document).querySelector(sel); }

    /**
     * Query selector all - returns array of all matching elements.
     * @param {string} sel - CSS selector to query
     * @param {Element|Document} [root=document] - Root element to search within
     * @returns {Array<Element>} Array of matching elements (empty array if none found)
     */
    function qsAll(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /**
     * Extracts the first word from a string for display purposes.
     * Used to personalize thank you messages with user's first name.
     * @param {string} s - Input string (typically full name)
     * @returns {string} First word trimmed to max 60 chars, or empty string if input is empty
     */
    function firstWord(s) { var t = String(s || '').trim(); return t ? t.split(/\s+/)[0].slice(0, 60) : ''; }

    /**
     * CRC32 lookup table — computed once at module load.
     * Implements the standard IEEE 802.3 reflected polynomial (0xEDB88320),
     * which is identical to PHP's built-in crc32() function.
     */
    var CRC32_TABLE = (function () {
        var t = new Uint32Array(256);
        for (var i = 0; i < 256; i++) {
            var c = i;
            for (var k = 0; k < 8; k++) {
                c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
            }
            t[i] = c;
        }
        return t;
    }());

    /**
     * Standard IEEE 802.3 CRC32 — output matches PHP's crc32() exactly.
     *
     * Returns an unsigned 32-bit integer (via >>> 0), mirroring PHP's
     * `crc32($str) & 0xFFFFFFFF` treatment, so both sides select the same
     * math question for a given formKey without further conversion.
     *
     * @param {string} str - ASCII input string (formKey is always ASCII)
     * @returns {number} Unsigned 32-bit CRC32 value
     */
    function crc32(str) {
        var crc = 0xFFFFFFFF;
        for (var i = 0; i < str.length; i++) {
            crc = CRC32_TABLE[(crc ^ str.charCodeAt(i)) & 0xFF] ^ (crc >>> 8);
        }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    /**
     * Initializes the math challenge field with deterministic question selection.
     *
     * Uses the same IEEE 802.3 CRC32 algorithm as PHP's crc32() so the browser
     * and server independently arrive at the same question index for a given
     * formKey. When formKey is present the server ignores the posted math_index
     * and recomputes it — the hidden field is only used as a fallback when
     * formKey is intentionally left blank in $CFG.
     *
     * @param {HTMLFormElement} form - The form containing the math challenge
     */
    function initMathChallenge(form) {
        if (!form) return;

        var questionEl = $('#math-question', form);
        var indexEl = form.querySelector('[name="math_index"]');
        var formKeyEl = form.querySelector('[name="form_key"]');

        if (!questionEl || !indexEl || !formKeyEl) return;

        var formKey = formKeyEl.value || '';
        var index = 0;

        if (formKey) {
            // crc32() returns an unsigned 32-bit value; no abs() needed.
            index = crc32(formKey) % mathQuestions.length;
        } else {
            // Fallback to random if no form key
            index = Math.floor(Math.random() * mathQuestions.length);
        }

        // Display the question and store the index
        questionEl.textContent = mathQuestions[index].question;
        indexEl.value = String(index);
    }

    /**
     * Checks if a specific error code is present in the URL query string.
     * Used to detect validation errors after PRG redirect from server.
     * @param {string} code - Error code to check for (e.g., 'email_invalid', 'too_fast')
     * @returns {boolean} True if the error code is in the URL, false otherwise
     */
    function hasErrCode(code) { return new RegExp('[?&]err=' + code + '(?:&|$)').test(location.search); }

    // ---------------------------------------------------------------------------
    // sessionStorage keys + helpers
    // ---------------------------------------------------------------------------
    var NS = 'gjf_';  // Namespace prefix to avoid collisions with other scripts
    var K = { tyName: NS + 'ty_name', tySubject: NS + 'ty_subject', draft: NS + 'draft', draftTs: NS + 'draft_ts' };
    var TTL = 30 * 60 * 1000;  // Time-to-live for drafts: 30 minutes in milliseconds

    /**
     * Sets a value in sessionStorage with error handling.
     * Silently fails if storage is unavailable (privacy mode, quota exceeded, etc.).
     * @param {string} k - Storage key
     * @param {string} v - Value to store
     */
    function Sset(k, v) { try { sessionStorage.setItem(k, v); } catch (_) { } }

    /**
     * Gets a value from sessionStorage with error handling.
     * @param {string} k - Storage key
     * @returns {string} Stored value or empty string if not found or error occurs
     */
    function Sget(k) { try { return sessionStorage.getItem(k) || ''; } catch (_) { return '' } }

    /**
     * Deletes a value from sessionStorage with error handling.
     * Silently fails if storage is unavailable.
     * @param {string} k - Storage key to remove
     */
    function Sdel(k) { try { sessionStorage.removeItem(k); } catch (_) { } }

    // ---------------------------------------------------------------------------
    // TY data API
    // ---------------------------------------------------------------------------
    window.Gjallarform = window.Gjallarform || {};

    /**
     * Stores thank you page data in sessionStorage for display after submission.
     * Called automatically on form submission before redirect to thank you page.
     * Data persists across the redirect and is consumed by renderThankYou().
     *
     * @param {Object} payload - Submission data object
     * @param {string} [payload.name] - Submitter's full name (first word will be extracted)
     * @param {string} [payload.subject] - Message subject (truncated to 120 chars)
     */
    window.Gjallarform.setThankYou = function (payload) {
        Sset(K.tyName, firstWord(payload.name || ''));
        Sset(K.tySubject, String(payload.subject || '').slice(0, 120));
    };

    /**
     * Renders personalized thank you message on the thank you page.
     * Reads data from sessionStorage (set by setThankYou), populates DOM elements,
     * makes the details box visible, then cleans up all stored data.
     *
     * Expected DOM structure on thank you page:
     * - #gjallarform-thankyou-details: Container to show (hidden by default)
     * - #gjallarform-thankyou-name: Element to display submitter's first name
     * - #gjallarform-thankyou-subject: Element to display message subject
     *
     * Cleans up: All thank you data, draft data, and timestamps from storage.
     */
    window.Gjallarform.renderThankYou = function () {
        var box = $('#gjallarform-thankyou-details'), n = $('#gjallarform-thankyou-name'), s = $('#gjallarform-thankyou-subject');
        var name = Sget(K.tyName), subj = Sget(K.tySubject);
        if (box && (name || subj)) {
            if (n) n.textContent = name || '—'; if (s) s.textContent = subj || '—';
            box.hidden = false;
        }
        [K.tyName, K.tySubject, K.draft, K.draftTs].forEach(Sdel);
    };

    // ---------------------------------------------------------------------------
    // Draft capture / rehydrate (only on ?err=…)
    // ---------------------------------------------------------------------------
    /**
     * Captures current form field values and saves to sessionStorage as a draft.
     * Called automatically on every form submission attempt (before validation).
     * Allows user to recover their input if validation fails and they're redirected back.
     *
     * @param {HTMLFormElement} form - The form element to snapshot
     */
    function snapshotForm(form) {
        var get = function (n) { var el = form.querySelector('[name="' + n + '"]'); return el ? el.value : '' };
        var p = { fullname: get('fullname'), email: get('email'), subject: get('subject'), message: get('message'), math_answer: get('math_answer') };
        Sset(K.draft, JSON.stringify(p)); Sset(K.draftTs, String(Date.now()));
    }

    /**
     * Checks if a saved draft is still valid based on TTL (time-to-live).
     * @returns {boolean} True if draft exists and is less than 30 minutes old, false otherwise
     */
    function validDraft() { var ts = parseInt(Sget(K.draftTs) || '0', 10); return ts && (Date.now() - ts) <= TTL; }

    /**
     * Restores form field values from a saved draft in sessionStorage.
     * Only restores if draft is valid (within TTL) and parseable.
     * Called automatically on page load when URL contains ?err=... parameter.
     * Silently fails if no valid draft exists or JSON parsing fails.
     *
     * @param {HTMLFormElement} form - The form element to populate with draft data
     */
    function rehydrate(form) {
        if (!validDraft()) return; var raw = Sget(K.draft); if (!raw) return;
        try {
            var d = JSON.parse(raw), set = function (n, v) { var el = form.querySelector('[name="' + n + '"]'); if (el) el.value = v || ''; };
            set('fullname', d.fullname); set('email', d.email); set('subject', d.subject); set('message', d.message); set('math_answer', d.math_answer);
        } catch (_) { }
    }

    // ---------------------------------------------------------------------------
    // Field-level helpers (native bubbles via setCustomValidity)
    // ---------------------------------------------------------------------------
    /**
     * Attaches ASCII-only validation to email input field.
     * Prevents submission of emails with non-ASCII characters (accented letters, emoji, etc.)
     * which may not be supported by all email systems. Uses native browser validation
     * bubbles via setCustomValidity() for consistent UX.
     *
     * If page loads with email-related error codes (?err=email_*), automatically
     * displays the validation bubble and focuses the field.
     *
     * @param {HTMLInputElement} input - Email input element to guard
     */
    function attachEmailAsciiGuard(input) {
        if (!input) return;

        /**
         * Validates email input for ASCII-only characters.
         * Sets custom validity message if non-ASCII found, clears it otherwise.
         * Empty values are allowed (handled by 'required' attribute).
         */
        function checkEmailAscii() {
            var v = input.value || '';
            if (!v) { input.setCustomValidity(''); return; } // required attribute handles empties
            if (/[^\x00-\x7F]/.test(v)) { input.setCustomValidity('Use standard ASCII email (no accented characters).'); return; }
            // Let the browser's type=email handle the rest
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

    /**
     * Attaches server-error handling to the math challenge answer input.
     * Surfaces math_wrong / math_invalid server codes as a native validation bubble
     * and clears the custom validity message when the user starts retyping.
     *
     * If page loads with math-related error codes (?err=math_*), automatically
     * displays the validation bubble and focuses the field.
     *
     * @param {HTMLInputElement} input - Math answer input element to guard
     */
    function attachMathChallengeGuard(input) {
        if (!input) return;

        // If server bounced with math_* codes, show error and focus field
        if (hasErrCode('math_wrong') || hasErrCode('math_invalid')) {
            var msg = hasErrCode('math_wrong') ? 'Incorrect answer. Please try again.' : 'Invalid answer format.';
            input.setCustomValidity(msg);
            setTimeout(function () {
                input.reportValidity && input.reportValidity();
                input.focus({ preventScroll: false });
            }, 0);
        }

        // Clear custom validity on input
        input.addEventListener('input', function() {
            input.setCustomValidity('');
        });
    }

    // ---------------------------------------------------------------------------
    // Submit: snapshot draft, rely on native validity, stash TY data when OK
    // ---------------------------------------------------------------------------
    /**
     * Global form submission handler.
     * Intercepts all form submissions on the page (capture phase) to:
     * 1. Save draft of form data (in case validation fails server-side)
     * 2. Perform client-side validation using native HTML5 validation
     * 3. Show native validation bubbles for invalid fields
     * 4. Store thank you data for display after successful submission
     *
     * If validation passes, form submits normally to server (PHP handler).
     * If validation fails, prevents submission and focuses first invalid field.
     */
    document.addEventListener('submit', function (ev) {
        var f = ev.target;
        if (!(f instanceof HTMLFormElement)) return;

        // Always save draft before submission attempt
        snapshotForm(f);

        // Client-side validation check
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

        // If validation passed, store data for thank you page
        var fullname = (f.querySelector('[name="fullname"]') || {}).value || '';
        var subject = (f.querySelector('[name="subject"]') || {}).value || '';
        window.Gjallarform.setThankYou({ name: fullname, subject });
    }, true);

    // ---------------------------------------------------------------------------
    // On load + bfcache handling
    // ---------------------------------------------------------------------------
    /**
     * DOMContentLoaded handler - initializes form on page load.
     *
     * For contact forms:
     * - Disables autocomplete (prevents browser from interfering with draft recovery)
     * - Sets render timestamp for server-side time-trap validation
     * - Attaches email ASCII validation
     * - Restores draft if error code in URL (?err=...), otherwise clears drafts
     *
     * For thank you page:
     * - Renders personalized thank you message if thank you root element exists
     */
    document.addEventListener('DOMContentLoaded', function () {
        var form = $('form.gjallarform');
        var hasErr = /[?&]err=/.test(location.search);

        if (form) {
            form.setAttribute('autocomplete', 'off');

            // Arm time-trap: set ms timestamp for the server check
            var tsEl = form.querySelector('[name="render_ts"]') || document.querySelector('[name="render_ts"]');
            if (tsEl) tsEl.value = String(Date.now());

            // Initialize math challenge
            initMathChallenge(form);

            // Attach field helpers
            attachEmailAsciiGuard($('[name="email"]', form));
            attachMathChallengeGuard($('[name="math_answer"]', form));

            // Surface server-side length rejections as field-level validation
            // bubbles. HTML maxlength prevents these in normal use; this handles
            // the case where limits were bypassed (e.g. via curl / dev tools).
            var lengthErrs = [
                { code: 'name_too_long',    sel: '[name="fullname"]', msg: 'Name must be 200 characters or fewer.'       },
                { code: 'email_too_long',   sel: '[name="email"]',    msg: 'Email must be 254 characters or fewer.'      },
                { code: 'subject_too_long', sel: '[name="subject"]',  msg: 'Subject must be 300 characters or fewer.'    },
                { code: 'message_too_long', sel: '[name="message"]',  msg: 'Message must be 10,000 characters or fewer.' }
            ];
            var firstLengthErrEl = null;
            lengthErrs.forEach(function (e) {
                if (hasErrCode(e.code)) {
                    var el = $(e.sel, form);
                    if (el) {
                        el.setCustomValidity(e.msg);
                        if (!firstLengthErrEl) firstLengthErrEl = el;
                    }
                }
            });
            if (firstLengthErrEl) {
                setTimeout(function () {
                    firstLengthErrEl.reportValidity && firstLengthErrEl.reportValidity();
                    firstLengthErrEl.focus({ preventScroll: false });
                }, 0);
            }

            if (hasErr) rehydrate(form); else[K.draft, K.draftTs].forEach(Sdel);
        }

        if (document.getElementById('gjallarform-thankyou-root')) {
            window.Gjallarform.renderThankYou();
        }
    });

    /**
     * Back-forward cache (bfcache) handler.
     *
     * When user navigates back to form via browser back button, browser may
     * restore page from cache (persisted=true). If there's no error in URL,
     * we reset the form and clear drafts to give a fresh start.
     *
     * This prevents stale data from appearing when user returns to form
     * after successful submission.
     *
     * @param {PageTransitionEvent} e - pageshow event with persisted property
     */
    window.addEventListener('pageshow', function (e) {
        var form = $('form.gjallarform'); var hasErr = /[?&]err=/.test(location.search);
        if (form && e.persisted && !hasErr) { try { form.reset(); } catch (_) { } [K.draft, K.draftTs].forEach(Sdel); }
    });
})();
