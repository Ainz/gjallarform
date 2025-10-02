# Conram Contact Form Engine

Most websites need a simple way for visitors to get in touch — a basic contact form.  
On shared hosting or static sites this often turns into unnecessary complexity:

- **External services** (Formspree, Google Forms, etc.) store user data outside your control.  
- **WordPress plugins** (WPForms, Contact Form 7, etc.) drag in a full CMS and constant patching overhead just to get one form.  
- **Ad-hoc scripts** with no security, validation, or audit trail.

This project exists to solve that problem:  
A **lightweight, secure, and host-friendly form engine** that works with static sites (like Publii) and shared hosting, without third-party dependencies.

---

## Features

### Backend (`backend/contact.php`)
- CORS restrictions (only allowed origins).  
- Honeypot, CSRF token, and render time-trap.  
- Per-IP rate limiting (10 posts / 30 min).  
- Validation rules:
  - **Name/Subject**: extended Latin letters allowed (å/ä/ö/ø/æ/é), emojis rejected.  
  - **Email**: validated via PHP filter.  
  - **Phone**: optional, digits + `+ - ( ) /`, 6–32 chars.  
  - **Message**: control chars stripped, emojis blocked, max 4000 chars.  
- Plain-text mail via PHP `mail()` with DMARC-aligned envelope (`form-engine@*`).  
- JSON responses (`{ok:true}` or `{ok:false,error:"…"}`).

### Debug endpoint (`backend/contact-echo.php`)
- Returns JSON dump of POST payloads for easy testing.

### Frontend (`frontend/contact-form.html`)
- Publii-safe HTML snippet.  
- Responsive CSS grid layout (2 columns desktop, single column mobile).  
- Placeholders and `title` attributes show character limits.  
- Hidden fields:  
  - `form_key`: anti-CSRF token (`conram_v1_2025_09`).  
  - `site_tag`: appears in mail subject (e.g. `[www.domain.tld Contact form]`).  
  - `render_ts`: JS-set timestamp for time-trap check.  
  - `website`: honeypot field.

---

## Integration Notes

- **Allowed origins**: `https://www.domain.tld`, `https://domain.tld`.  
- **Mail envelope**: sent from `form-engine@*` to the target recipient (e.g. `rikard.malmborg@conram.it`).  
- **Limits**:  
  - 10 messages / 30 minutes / IP.  
  - Server sendmail capped at ~50/day by host rules.  
- **Setup**:  
  - Deploy backend PHP files to a subdomain (e.g. `service.domain.tld`).  
  - Configure SPF, DKIM, and DMARC for sending domain.  
  - Insert the HTML form snippet into your static site.  
  - Test via `contact-echo.php`, then switch to `contact.php`.

---

## Why not PHPMailer or WordPress?

- **PHPMailer/SMTP**: powerful, but adds dependencies and setup complexity. For plain-text mail on shared hosting, `mail()` is sufficient if SPF/DKIM/DMARC are correct.  
- **WordPress plugins**: require running a full CMS for something trivial.  
- **External services**: introduce privacy/compliance risks and external points of failure.  

The Conram approach: **use what’s already available (PHP + sendmail), secure it, and keep it lean.**

---

## Documentation

- [`docs/PREREQUISITES.md`](docs/PREREQUISITES.md) – setup requirements and host considerations.  
- [`docs/FORM_FLOW.md`](docs/FORM_FLOW.md) – how submissions are processed.  
- [`docs/CHANGELOG.md`](docs/CHANGELOG.md) – version history.

---

## Roadmap

- Client-side error messages mapping backend codes.  
- Optional live character counters via external JS (`data-count`).  
- Logging submissions to file/DB (GDPR-aware).  
- Switchable PHPMailer/SMTP transport for advanced cases.

---

## License

Multiple Licensing, see License for details.
