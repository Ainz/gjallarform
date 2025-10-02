This project provides:

# Conram.it Contact Form Engine

Most websites need a simple way for visitors to get in touch — a basic contact form.  
Yet on shared hosting or static sites this often turns into unnecessary complexity:

- External services (Formspree, Google Forms, etc.) that store your users' data outside your control.  
- WordPress plugins like WPForms or Contact Form 7, which drag in a CMS, plugins, and constant patching overhead just for one form.  
- Ad-hoc scripts with no security, validation, or audit trail.  

This project exists to solve that problem: **a lightweight, secure, and host-friendly form engine** that works with static sites (like Publii) and shared hosting, without third-party dependencies.

---

## Features

- **Secure backend (`contact.php`)**
  - Origin (CORS) restriction to allowed domains.
  - Honeypot field, CSRF token, and time-trap check.
  - Per-IP rate limiting.
  - Strict validation:
    - Name/Subject: extended Latin letters allowed, emojis rejected.
    - Email: RFC-style validation.
    - Phone: optional, digits + `+ - ( ) /`, 6–32 chars.
    - Message: control chars stripped, emojis blocked, 4000 chars max.
  - Plain-text mail via PHP `mail()` with DMARC-aligned sender.

- **Frontend snippet (`contact-form.html`)**
  - Publii-safe HTML block.
  - Responsive CSS grid layout.
  - Tooltips (`title`) show max character counts.
  - Hidden fields for token, tag, and render timestamp.

- **Debug endpoint (`contact-echo.php`)**
  - Returns JSON dump of POST payloads for testing.

- **Documentation**
  - [`docs/PREREQUISITES.md`](docs/PREREQUISITES.md) – setup requirements and host considerations.
  - [`docs/FORM_FLOW.md`](docs/FORM_FLOW.md) – how submissions are processed.
  - [`docs/CHANGELOG.md`](docs/CHANGELOG.md) – version history.

---

## Why not PHPMailer or WordPress?

- **PHPMailer** is powerful, but adds dependencies and setup complexity. For plain-text messages on shared hosting, PHP `mail()` is sufficient if SPF/DKIM/DMARC are configured.  
- **WordPress plugins** work, but require running a full CMS and patching ecosystem for something as trivial as a contact form.  
- **External form services** introduce privacy and compliance risks, and add a single point of failure outside your infrastructure.  

The Conram.it approach: **use what’s already available (PHP + sendmail), secure it, and keep it lean.**

---

## Usage

1. Deploy `backend/contact.php` and `backend/contact-echo.php` to a subdomain (e.g. `service.domain.tld`).  
2. Configure DNS for SPF, DKIM, DMARC.  
3. Insert the HTML snippet (`frontend/contact-form.html`) into your static site (Publii, Hugo, plain HTML).  
4. Test using `contact-echo.php`, then switch to `contact.php`.  

---

## Roadmap

- Client-side error message mapping.  
- Optional live character counters via linked JS (`data-count`).  
- Logging of submissions (file or DB, GDPR-aware).  
- Switchable PHPMailer/SMTP transport for advanced use cases.  

---

## License

Internal use only. Not licensed for public redistribution.



- **Backend** (`contact.php`):  
  Validates and relays contact form submissions via sendmail, with DMARC-aligned envelope.  
  - Origin restrictions (CORS, allowed domains).  
  - Honeypot, CSRF token, and time-trap protections.  
  - Rate-limiting (max 10 posts / 30 minutes / IP).  
  - Validation:
    - Name & Subject: extended Latin allowed (å/ä/ö/ø/æ/é), emojis rejected.  
    - Email: RFC-ish filter.  
    - Phone: optional, digits + `+ - ( ) /`, 6–32 chars.  
    - Message: control chars stripped, emojis rejected, 4000 chars max.  
  - JSON responses (`ok:true` or `error:…`).  

- **Echo endpoint** (`contact-echo.php`):  
  Debug tool to dump POST payloads for form integration testing.

- **Frontend snippets** (`frontend/contact-form.html`):  
  Publii-safe HTML form with inline titles for character limits and placeholders.  
  Styled with minimal grid CSS, responsive (2 columns desktop, single column mobile).  
---

## Integration Notes

- **Hidden fields** required by backend:
  - `form_key`: current token (`conram_v1_2025_09`).
  - `site_tag`: appears in mail subject (e.g. `[www.conram.it Contact form]`).
  - `render_ts`: JavaScript-set timestamp, used for time-trap check.

- **Allowed origins**:  
  `https://www.conram.it` and `https://conram.it`.

- **Mail envelope**:  
  Sent from `form-engine@conram.it` to `rikard.malmborg@conram.it`.

- **Limits**:
  - 10 messages / 30 minutes per IP.
  - Server sendmail capped at ~50/day for this account (host rule).

---

## Roadmap

- Add friendly client-side error messages mapping backend error codes.  
- Consider linked JS for live character counters (`data-count` attributes already in place).  
- Optionally log messages to a database or JSON file for archival.  

---

## License

Internal project use. Not licensed for public redistribution.