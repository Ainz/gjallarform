/*!
 * Gjallarform - Contact Form Solution
 * Version: 0.96-beta
 * Copyright (C) 2026 Rikard Malmborg / CONRAM.IT
 * GPL-3.0-or-later - https://github.com/Ainz/gjallarform
 */

(function () {
    'use strict';

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
.gjallarform button:disabled{cursor:wait;opacity:.65}
.gjallarform__hp{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}
.gjallarform__status{margin:.25rem 0}
.gjallarform__privacy{font-size:.9em}
@media (max-width:768px){.gjallarform{grid-template-columns:1fr}}
@container gjallarformwrapper (max-width:600px){.gjallarform{grid-template-columns:1fr}}
`;

    if (!document.getElementById('gjallarform-style')) {
        var style = document.createElement('style');
        style.id = 'gjallarform-style';
        style.textContent = CSS;
        document.head.appendChild(style);
    }

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function firstWord(value) {
        var match = String(value || '').trim().match(/^\S+/);
        return match ? match[0] : '';
    }

    function hasErrCode(code) {
        return new RegExp('[?&]err=' + code + '(?:&|$)').test(location.search);
    }

    var NS = 'gjf_';
    var K = {
        tyName: NS + 'ty_name',
        tySubject: NS + 'ty_subject',
        tyConfirmation: NS + 'ty_confirmation',
        draft: NS + 'draft',
        draftTs: NS + 'draft_ts'
    };
    var TTL = 30 * 60 * 1000;
    var challengeState = { confirmationEnabled: false };

    function Sset(key, value) {
        try { sessionStorage.setItem(key, value); } catch (_) { }
    }

    function Sget(key) {
        try { return sessionStorage.getItem(key) || ''; } catch (_) { return ''; }
    }

    function Sdel(key) {
        try { sessionStorage.removeItem(key); } catch (_) { }
    }

    window.Gjallarform = window.Gjallarform || {};

    window.Gjallarform.setThankYou = function (payload) {
        Sset(K.tyName, firstWord(payload.name || ''));
        Sset(K.tySubject, String(payload.subject || '').slice(0, 120));
        Sset(K.tyConfirmation, payload.confirmationEnabled ? '1' : '0');
    };

    window.Gjallarform.renderThankYou = function () {
        var box = $('#gjallarform-thankyou-details');
        var nameEl = $('#gjallarform-thankyou-name');
        var subjectEl = $('#gjallarform-thankyou-subject');
        var confirmation = $('#gjallarform-confirmation-note');
        var name = Sget(K.tyName);
        var subject = Sget(K.tySubject);

        if (box && (name || subject)) {
            if (nameEl) nameEl.textContent = name || '-';
            if (subjectEl) subjectEl.textContent = subject || '-';
            box.hidden = false;
        }
        if (confirmation && Sget(K.tyConfirmation) === '1') confirmation.hidden = false;

        [K.tyName, K.tySubject, K.tyConfirmation, K.draft, K.draftTs].forEach(Sdel);
    };

    function snapshotForm(form) {
        function get(name) {
            var field = form.querySelector('[name="' + name + '"]');
            return field ? field.value : '';
        }
        var payload = {
            fullname: get('fullname'),
            email: get('email'),
            subject: get('subject'),
            message: get('message'),
            math_answer: get('math_answer')
        };
        Sset(K.draft, JSON.stringify(payload));
        Sset(K.draftTs, String(Date.now()));
    }

    function validDraft() {
        var timestamp = parseInt(Sget(K.draftTs) || '0', 10);
        return Boolean(timestamp && Date.now() - timestamp <= TTL);
    }

    function rehydrate(form) {
        if (!validDraft()) return;
        var raw = Sget(K.draft);
        if (!raw) return;
        try {
            var draft = JSON.parse(raw);
            ['fullname', 'email', 'subject', 'message', 'math_answer'].forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (field) field.value = draft[name] || '';
            });
        } catch (_) { }
    }

    function attachEmailGuard(input) {
        if (!input) return;
        function validate() {
            var value = input.value || '';
            if (!value) { input.setCustomValidity(''); return; }
            if (/[^\x00-\x7F]/.test(value)) {
                input.setCustomValidity('Use a standard ASCII email address.');
            } else if (!/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/.test(value)) {
                input.setCustomValidity('Please enter a valid email address.');
            } else {
                input.setCustomValidity('');
            }
        }
        input.addEventListener('input', validate);
        input.addEventListener('blur', validate);
        if (hasErrCode('email_ascii_only') || hasErrCode('email_invalid')) {
            validate();
            setTimeout(function () {
                if (input.reportValidity) input.reportValidity();
                input.focus({ preventScroll: false });
            }, 0);
        }
    }

    function attachMathGuard(input) {
        if (!input) return;
        if (hasErrCode('math_wrong')) {
            input.setCustomValidity('Incorrect answer. Please try again.');
            setTimeout(function () {
                if (input.reportValidity) input.reportValidity();
                input.focus({ preventScroll: false });
            }, 0);
        }
        input.addEventListener('input', function () { input.setCustomValidity(''); });
    }

    function statusMessageFromUrl() {
        if (hasErrCode('rate_limited')) return 'Too many attempts. Please wait before trying again.';
        if (hasErrCode('challenge_expired')) return 'The form session expired. A fresh check has been loaded; please submit again.';
        if (hasErrCode('challenge_invalid')) return 'The form check was invalid. A fresh check has been loaded; please submit again.';
        if (hasErrCode('too_fast')) return 'Please take a moment to review the form, then submit again.';
        if (hasErrCode('send_failed')) return 'The message could not be sent. Please try again later.';
        return '';
    }

    function setStatus(form, message, isError) {
        var status = $('.gjallarform__status', form);
        if (!status) return;
        status.textContent = message;
        status.setAttribute('role', isError ? 'alert' : 'status');
    }

    function loadChallenge(form) {
        var token = form.querySelector('[name="challenge_token"]');
        var question = $('#math-question', form);
        var mathLabel = $('.gjallarform__math', form);
        var answer = form.querySelector('[name="math_answer"]');
        var submit = form.querySelector('[type="submit"]');
        if (!token || !question || !mathLabel || !answer || !submit) return Promise.reject(new Error('Incomplete form markup'));

        submit.disabled = true;
        form.dataset.gjallarformReady = 'false';
        token.value = '';
        setStatus(form, 'Preparing the anti-abuse check...', false);

        var endpoint = new URL(form.action, window.location.href);
        endpoint.searchParams.set('gjf_challenge', '1');

        return fetch(endpoint.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Challenge request failed: ' + response.status);
            return response.json();
        }).then(function (data) {
            if (!data || typeof data.token !== 'string' || !data.token) throw new Error('Invalid challenge response');
            token.value = data.token;
            challengeState.confirmationEnabled = data.confirmationEnabled === true;

            if (data.mathEnabled === true && typeof data.question === 'string') {
                question.textContent = data.question;
                mathLabel.hidden = false;
                answer.required = true;
            } else {
                question.textContent = '';
                mathLabel.hidden = true;
                answer.required = false;
                answer.value = '';
            }

            form.dataset.gjallarformReady = 'true';
            submit.disabled = false;
            setStatus(form, statusMessageFromUrl(), Boolean(statusMessageFromUrl()));
        }).catch(function (error) {
            console.error('Gjallarform:', error);
            setStatus(form, 'The form could not start. Please reload the page or try again later.', true);
            throw error;
        });
    }

    function attachLengthErrors(form) {
        var errors = [
            { code: 'name_too_long', selector: '[name="fullname"]', message: 'Name must be 200 characters or fewer.' },
            { code: 'email_too_long', selector: '[name="email"]', message: 'Email must be 254 characters or fewer.' },
            { code: 'subject_too_long', selector: '[name="subject"]', message: 'Subject must be 300 characters or fewer.' },
            { code: 'message_too_long', selector: '[name="message"]', message: 'Message must be 10,000 characters or fewer.' }
        ];
        var first = null;
        errors.forEach(function (error) {
            if (!hasErrCode(error.code)) return;
            var field = $(error.selector, form);
            if (!field) return;
            field.setCustomValidity(error.message);
            if (!first) first = field;
            field.addEventListener('input', function () { field.setCustomValidity(''); }, { once: true });
        });
        if (first) {
            setTimeout(function () {
                if (first.reportValidity) first.reportValidity();
                first.focus({ preventScroll: false });
            }, 0);
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('form.gjallarform')) return;

        snapshotForm(form);
        if (form.dataset.gjallarformReady !== 'true' || !form.querySelector('[name="challenge_token"]').value) {
            event.preventDefault();
            setStatus(form, 'The anti-abuse check is not ready. Please wait or reload the page.', true);
            return;
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            var invalid = form.querySelector(':invalid');
            if (invalid && invalid.reportValidity) {
                invalid.reportValidity();
                invalid.focus({ preventScroll: true });
            } else if (form.reportValidity) {
                form.reportValidity();
            }
            return;
        }

        var fullname = (form.querySelector('[name="fullname"]') || {}).value || '';
        var subject = (form.querySelector('[name="subject"]') || {}).value || '';
        window.Gjallarform.setThankYou({
            name: fullname,
            subject: subject,
            confirmationEnabled: challengeState.confirmationEnabled
        });
        form.dataset.gjallarformReady = 'false';
        var submit = form.querySelector('[type="submit"]');
        if (submit) submit.disabled = true;
        setStatus(form, 'Sending message...', false);
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        var form = $('form.gjallarform');
        var hasError = /[?&]err=/.test(location.search);

        if (form) {
            form.setAttribute('autocomplete', 'off');
            if (hasError) rehydrate(form); else [K.draft, K.draftTs].forEach(Sdel);
            attachEmailGuard($('[name="email"]', form));
            attachMathGuard($('[name="math_answer"]', form));
            attachLengthErrors(form);
            loadChallenge(form).catch(function () { });
        }

        if (document.getElementById('gjallarform-thankyou-root')) {
            window.Gjallarform.renderThankYou();
        }
    });

    window.addEventListener('pageshow', function (event) {
        var form = $('form.gjallarform');
        var hasError = /[?&]err=/.test(location.search);
        if (form && event.persisted) {
            if (!hasError) {
                try { form.reset(); } catch (_) { }
                [K.draft, K.draftTs].forEach(Sdel);
            }
            loadChallenge(form).catch(function () { });
        }
    });
})();
