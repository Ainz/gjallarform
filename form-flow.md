# Form Flow

This document describes how the Conram Contact Form Engine handles a submission
from the browser to delivery as an email.

---
Form Flow applies to v1.0.0
---

## Sequence

1. **User fills the form** on a Publii page (e.g. `https://www.conram.it/contact-form.html`).
   - Required fields: `fullname`, `email`, `subject`, `message`.
   - Optional: `phone`.
   - Hidden fields: `form_key`, `site_tag`, `render_ts`, `website` (honeypot).

2. **Browser submits POST** to  
   `https://service.conram.it/contact.php`.

3. **Backend validation** (`contact.php`):
   - **Origin check:** only accepts requests from `conram.it` domains.
   - **Honeypot:** if `website` field is filled → silent discard.
   - **Token check:** `form_key` must match current version.
   - **Time-trap:** submission must take at least ~2 seconds from page render.
   - **Rate-limit:** max 10 submissions per 30 minutes per IP.
   - **Field validation:**
     - Name & Subject: extended Latin allowed, emojis rejected.
     - Email: RFC-style validation.
     - Phone: optional, must match digits + `+ - ( ) /`, 6–32 chars.
     - Message: stripped of control chars, emojis rejected, max 4000 chars.

4. **Mail composition:**
   - From: `form-engine@conram.it`
   - To: `rikard.malmborg@conram.it`
   - Reply-To: user’s email
   - Subject: `[site_tag] Subject`
   - Body includes name, email, phone, IP, and message.

5. **Sendmail delivery**:
   - Uses `mail()` with `-f` flag for DMARC/SPF alignment.
   - Host enforces ~50/day message cap.

6. **Response to client**:
   - On success: `{ "ok": true }`.
   - On failure: `{ "ok": false, "error": "…" }`
     - Possible errors: `forbidden_origin`, `bad_key`, `too_fast`,
       `rate_limited`, `validation`, `name_charset`, `subject_charset`,
       `message_emoji_blocked`, `phone_invalid`, `send_failed`.

---

## Diagram

[User Form]
↓ (POST)
[contact.php]
├─ Origin check
├─ Honeypot / Key / Time-trap
├─ Rate-limit
└─ Validation
↓
[Sendmail]
↓
[rikard.malmborg@conram.it
]

---

## Notes
- Frontend snippets (`frontend/contact-form.html`) must preserve field names.  
- Tokens (`form_key`, `site_tag`, `render_ts`) are required.  
- Debugging can be done via `contact-echo.php` (returns JSON dump of POST).  
