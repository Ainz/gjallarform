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
        { question: 'What is 5 + 3?',   answer: '8' },
        { question: 'What is 10 - 4?',  answer: '6' },
        { question: 'What is 6 × 2?',   answer: '12' },
        { question: 'What is 15 ÷ 3?',  answer: '5' },
        { question: 'What is 7 + 8?',   answer: '15' },
        { question: 'What is 20 - 11?', answer: '9' },
        { question: 'What is 4 × 3?',   answer: '12' },
        { question: 'What is 9 + 6?',   answer: '15' },
        { question: 'What is 13 - 5?',  answer: '8' },
        { question: 'What is 3 × 7?',   answer: '21' },
        { question: 'What is 8 + 4?',   answer: '12' },
        { question: 'What is 17 - 9?',  answer: '8' },
        { question: 'What is 5 × 4?',   answer: '20' },
        { question: 'What is 6 + 9?',   answer: '15' },
        { question: 'What is 18 - 7?',  answer: '11' }
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
     * Simple MD5-based hash returning an unsigned 32-bit integer.
     * Mirrors the PHP seeding: hexdec(substr(md5(formKey + host), 0, 8)).
     * Uses a lightweight MD5 port — only needs to match PHP for question selection.
     */
    function md5Seed(str) {
        // SparkMD5-free single-function MD5 sufficient for non-crypto seeding.
        // Based on the RFC 1321 reference implementation, trimmed to 32-bit output.
        function safeAdd(x, y) { var lsw = (x & 0xFFFF) + (y & 0xFFFF); return (((x >> 16) + (y >> 16) + (lsw >> 16)) << 16) | (lsw & 0xFFFF); }
        function bitRotateLeft(num, cnt) { return (num << cnt) | (num >>> (32 - cnt)); }
        function md5cmn(q, a, b, x, s, t) { return safeAdd(bitRotateLeft(safeAdd(safeAdd(a, q), safeAdd(x, t)), s), b); }
        function md5ff(a,b,c,d,x,s,t){return md5cmn((b&c)|((~b)&d),a,b,x,s,t);}
        function md5gg(a,b,c,d,x,s,t){return md5cmn((b&d)|(c&(~d)),a,b,x,s,t);}
        function md5hh(a,b,c,d,x,s,t){return md5cmn(b^c^d,a,b,x,s,t);}
        function md5ii(a,b,c,d,x,s,t){return md5cmn(c^(b|(~d)),a,b,x,s,t);}
        // Encode string to array of little-endian 32-bit words
        var length8 = str.length * 8;
        var x = [];
        for (var i = 0; i < str.length; i++) { x[i >> 2] |= (str.charCodeAt(i) & 0xFF) << ((i % 4) * 8); }
        x[str.length >> 2] |= 0x80 << ((str.length % 4) * 8);
        x[(((str.length + 8) >> 6) << 4) + 14] = length8 & 0xFFFFFFFF;
        x[(((str.length + 8) >> 6) << 4) + 15] = Math.floor(length8 / 0x100000000);
        var a = 1732584193, b = -271733879, c = -1732584194, d = 271733878;
        for (var i = 0; i < x.length; i += 16) {
            var A=a,B=b,C=c,D=d;
            a=md5ff(a,b,c,d,x[i+0],7,-680876936);d=md5ff(d,a,b,c,x[i+1],12,-389564586);c=md5ff(c,d,a,b,x[i+2],17,606105819);b=md5ff(b,c,d,a,x[i+3],22,-1044525330);
            a=md5ff(a,b,c,d,x[i+4],7,-176418897);d=md5ff(d,a,b,c,x[i+5],12,1200080426);c=md5ff(c,d,a,b,x[i+6],17,-1473231341);b=md5ff(b,c,d,a,x[i+7],22,-45705983);
            a=md5ff(a,b,c,d,x[i+8],7,1770035416);d=md5ff(d,a,b,c,x[i+9],12,-1958414417);c=md5ff(c,d,a,b,x[i+10],17,-42063);b=md5ff(b,c,d,a,x[i+11],22,-1990404162);
            a=md5ff(a,b,c,d,x[i+12],7,1804603682);d=md5ff(d,a,b,c,x[i+13],12,-40341101);c=md5ff(c,d,a,b,x[i+14],17,-1502002290);b=md5ff(b,c,d,a,x[i+15],22,1236535329);
            a=md5gg(a,b,c,d,x[i+1],5,-165796510);d=md5gg(d,a,b,c,x[i+6],9,-1069501632);c=md5gg(c,d,a,b,x[i+11],14,643717713);b=md5gg(b,c,d,a,x[i+0],20,-373897302);
            a=md5gg(a,b,c,d,x[i+5],5,-701558691);d=md5gg(d,a,b,c,x[i+10],9,38016083);c=md5gg(c,d,a,b,x[i+15],14,-660478335);b=md5gg(b,c,d,a,x[i+4],20,-405537848);
            a=md5gg(a,b,c,d,x[i+9],5,568446438);d=md5gg(d,a,b,c,x[i+14],9,-1019803690);c=md5gg(c,d,a,b,x[i+3],14,-187363961);b=md5gg(b,c,d,a,x[i+8],20,1163531501);
            a=md5gg(a,b,c,d,x[i+13],5,-1444681467);d=md5gg(d,a,b,c,x[i+2],9,-51403784);c=md5gg(c,d,a,b,x[i+7],14,1735328473);b=md5gg(b,c,d,a,x[i+12],20,-1926607734);
            a=md5hh(a,b,c,d,x[i+5],4,-378558);d=md5hh(d,a,b,c,x[i+8],11,-2022574463);c=md5hh(c,d,a,b,x[i+11],16,1839030562);b=md5hh(b,c,d,a,x[i+14],23,-35309556);
            a=md5hh(a,b,c,d,x[i+1],4,-1530992060);d=md5hh(d,a,b,c,x[i+4],11,1272893353);c=md5hh(c,d,a,b,x[i+7],16,-155497632);b=md5hh(b,c,d,a,x[i+10],23,-1094730640);
            a=md5hh(a,b,c,d,x[i+13],4,681279174);d=md5hh(d,a,b,c,x[i+0],11,-358537222);c=md5hh(c,d,a,b,x[i+3],16,-722521979);b=md5hh(b,c,d,a,x[i+6],23,76029189);
            a=md5hh(a,b,c,d,x[i+9],4,-640364487);d=md5hh(d,a,b,c,x[i+12],11,-421815835);c=md5hh(c,d,a,b,x[i+15],16,530742520);b=md5hh(b,c,d,a,x[i+2],23,-995338651);
            a=md5ii(a,b,c,d,x[i+0],6,-198630844);d=md5ii(d,a,b,c,x[i+7],10,1126891415);c=md5ii(c,d,a,b,x[i+14],15,-1416354905);b=md5ii(b,c,d,a,x[i+5],21,-57434055);
            a=md5ii(a,b,c,d,x[i+12],6,1700485571);d=md5ii(d,a,b,c,x[i+3],10,-1894986606);c=md5ii(c,d,a,b,x[i+10],15,-1051523);b=md5ii(b,c,d,a,x[i+1],21,-2054922799);
            a=md5ii(a,b,c,d,x[i+8],6,1873313359);d=md5ii(d,a,b,c,x[i+15],10,-30611744);c=md5ii(c,d,a,b,x[i+6],15,-1560198380);b=md5ii(b,c,d,a,x[i+13],21,1309151649);
            a=md5ii(a,b,c,d,x[i+4],6,-145523070);d=md5ii(d,a,b,c,x[i+11],10,-1120210379);c=md5ii(c,d,a,b,x[i+2],15,718787259);b=md5ii(b,c,d,a,x[i+9],21,-343485551);
            a=safeAdd(a,A);b=safeAdd(b,B);c=safeAdd(c,C);d=safeAdd(d,D);
        }
        // Return first 8 hex chars as an unsigned 32-bit integer (mirrors hexdec(substr(md5(...),0,8)))
        var hex = ('00000000' + (a >>> 0).toString(16)).slice(-8);
        return parseInt(hex, 16);
    }

    /**
     * Initializes the math challenge field with deterministic question selection.
     *
     * Seeds from formKey + hostname (window.location.hostname) to mirror the PHP
     * logic (formKey + HTTP_HOST), so different installations on different domains
     * land on different questions even with identical formKeys. The server ignores
     * the posted math_index when formKey is set and recomputes it independently.
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
            var seed = md5Seed(formKey + window.location.hostname);
            index = seed % mathQuestions.length;
        } else {
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
