This project provides:

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