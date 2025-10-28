# Contactulus – Conram Contact Form Engine

Most websites need a simple way for visitors to get in touch — a reliable contact form.
On static or shared hosting this often becomes over-engineered:

## External services store data outside your control.

- CMS plugins drag in dependencies and maintenance overhead.
- Random scripts often lack validation, sanitization, or privacy safeguards.

Contactulus solves this by being a lightweight, host-friendly, privacy-respecting contact form engine.
It runs on plain PHP + sendmail and works perfectly with static site generators such as Publii — no database, no external API, no vendor lock-in.

## Features
- Server-side (/contactulus/contact.php)
- Honeypot field (website) – silent discard of bot posts.
- Optional form key – shared secret between form and handler.
- Configurable time-trap – requires a minimum render→submit delay (anti-bot).

### Field validation
- fullname / name – non-empty.
- email – ASCII-only, single @, basic RFC-style regex.
- message – non-empty, trimmed to safe plain text.
- PRG pattern – POST → 303 redirect to thank-you.html.

### Email delivery
- Admin notification → $CFG['to'].
- User confirmation (“Copy of your message”).
- Envelope sender -f $CFG['from'] for SPF/DMARC alignment.

### Simple configuration in the $CFG block:
- 'to'              => 'admin@example.com',
- 'from'            => 'form-engine@example.com',
- 'formKey'         => 'conram-871297',
- 'timeTrapEnabled' => true,
- 'timeTrapMinMs'   => 2000,
- 'timezone'        => 'Europe/Stockholm'

### No external libraries, no dependencies, no tracking.

- Client-side (/contactulus/contactulus.js)
- Injected CSS (safe for Publii sanitization).
- Render-time stamp (render_ts) written in milliseconds for the server trap.
- Draft cache – field values preserved in sessionStorage during error bounces.
- Friendly validation bubbles
- Native <input type="email"> shape check.
- Custom ASCII-only notice via setCustomValidity().
- Thank-You page data – displays name, subject, and reference from session data.
- Back/Forward cache awareness – resets cleanly after successful send.
- Path-agnostic – can be mounted in any folder (/contactulus/, /form/, etc.).

### Form HTML
Minimal working form:
```html
<form class="cf" action="/contactulus/contact.php" method="post" novalidate>
  <label>Name <input type="text" name="fullname" required></label>
  <label>Email <input type="email" name="email" required></label>
  <label>Subject <input type="text" name="subject" required></label>
  <label class="cf__span-2">Message <textarea name="message" required></textarea></label>

  <input type="hidden" name="render_ts" value="">
  <input type="hidden" name="form_key" value="conram-871297">
  <input type="text" name="website" class="hp" autocomplete="off">

  <div class="cf__actions cf__span-2">
    <button type="submit">Send message</button>
  </div>
</form>

<script src="/contactulus/contactulus.js" defer></script>
```

## Integration Notes
- Deploy /contactulus/ to the same origin as your static site.
- Update $CFG['to'] and $CFG['from'] in contact.php.
- Ensure your domain’s SPF/DKIM/DMARC cover the from address.
- Optional monitoring endpoint:
```php
// /contactulus/healthcheck.php
header('Content-Type:text/plain'); echo 'OK ', gmdate('Y-m-d\TH:i:s\Z');
```
- Works with any PHP ≥ 7.4 host that supports mail().

### Current Architecture
```bash
/                (site root)
├─ contact-form.html
├─ thank-you.html
└─ contactulus/
   ├─ contact.php          # main handler (server-side)
   ├─ contactulus.js       # client logic (injects CSS + time-trap)
```
### Roadmap
- Map all server ?err= codes to inline field hints (UX polish).
- Optional per-IP rate limiting in PHP tempfiles.
- Form-agnostic “field walker” (dropdowns, checkboxes, multi-value capture).
- Optional HTML-formatted confirmation mail.
- Packaging /dist ZIP with example and quick-deploy docs.

## License
Conram Contactulus © 2025 Conram.it — All rights reserved.
Dual licensing available; see LICENSE for details.
