# Form Flow
Applies to the current **local** Contactulus setup (self-contained in  `/contactulus/` folder).

Note: `contact-echo.php` has been **removed**.

---
## 1) Frontend (static page → local handler)
A static site form (Publii/Hugo/plain HTML) posts to the **local** endpoint: https://www.domain.tld/contactulus/contact.php
Include the UI helper once (after the form is fine):

```html
<script src="/contactulus/contactulus.js" defer></script>
```
### Hidden fields (anti-abuse helpers)
- form_key → anti-CSRF/anti-random-post token (rotate when needed).
- render_ts → JS sets milliseconds-on-render; used as a time-trap.
- website → honeypot field (must remain empty).
Example:
```html
<input type="hidden" name="form_key" value="conram_v1_2025_09">
<input type="hidden" name="render_ts" value="">
<script>
  document.currentScript.previousElementSibling.value = String(Date.now());
</script>
```
---
## 2) Backend validation `/contactulus/contact.php`
- Method gate: POST-only; GET requests are rejected (or optionally redirected to the form).
- Time-trap: rejects submissions made too quickly (render_ts < 2s).
- Honeypot: if website is non-empty → silent success (redirect to TY, no mail sent).
- Form key: if present and wrong → validation error.
Fields:
- fullname (or name) → required (trimmed).
- email → required; ASCII-ish regex check (browser validates too).
- subject → optional; defaults to DOMAIN.TLD Contact.
- phone → optional; digits + + - ( ) /.
- message → required; max ~4000 chars (enforced by form).
Rate limiting and CORS checks are not enabled by default in this minimal build.
They can be added later without changing the user experience.
---
## 3) Mail delivery (two messages)
Sent via PHP mail() through your host’s MTA.
Envelope sender (Return-Path) is set for deliverability:
```php
$envelope = "-f form-engine@conram.it";
```
Admin mail
- To: configured recipient (e.g., recipient@domain.tld)
- From: form-mail@domain.tld
- Reply-To: user’s email
- Subject: [DOMAIN.TLD Contact] [REF] {subject}
- Body: includes reference ID, meta, and message.
Submitter confirmation (receipt)
- To: the email the user entered
- From: form-mail@domain.tld
- Reply-To: site inbox (so replies come back to you)
- Subject: Copy of your message — Organisation [REF]
- Body: full copy of their submission (summary + original message), with timestamp.
DKIM/SPF/DMARC must be configured on domain.tld.
With the envelope sender and domain-aligned From, deliverability is solid.
---
## 4) Responses / navigation
PRG (Post/Redirect/Get):
- On success → 303 redirect to:
```html
https://www.domain.tld/thank-you.html
```
- On validation/anti-abuse failure → 303 back to the form with ?err=....
No JSON/AJAX mode in the minimal build (kept simple for static sites).
---
## 5) Thank-you page
A simple static page at /thank-you.html.
(Optional) If you include the expected IDs, the JS can personalize it via sessionStorage.
This is optional and typically disabled in production.

## 6) Debug / testing (since contact-echo.php is removed)
Use curl (linux) to test end-to-end:
```bash
curl -i -X POST https://www.domain.tld/contactulus/contact.php \
  -F 'fullname=Test Person' \
  -F 'email=test@example.com' \
  -F 'subject=Hello' \
  -F 'message=This is a test.' \
  -F 'form_key=conram_v1_2025_09' \
  -F "render_ts=$(date +%s%3N)"
```

Expected: HTTP/2 303 with
Location: https://www.domain.tld/thank-you.html.
Admin receives the notification; submitter receives the confirmation copy.

---

¤¤ Notes
- Keep contact.php free of any output (no BOM/whitespace) before headers.
- Maintain two separate header arrays in PHP (admin vs confirmation).
- The honeypot uses a generic name (website); if autofill ever touches it, consider renaming to a neutral contact_human and accept both names server-side for backward compatibility.

EOF
