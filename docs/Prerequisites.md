# Prerequisites & Limitations

This document lists what is required for running the Conram Contact Form Engine, and outlines current limitations.

---

## Domain & Email

- **Dedicated sender mailbox** (recommended): used here `form-engine@*`  
  - Used for DMARC alignment, filtering, and avoiding mixing with personal mail.  
- **DNS for deliverability (mandatory):**
  - **SPF**: must include your host’s outbound mail servers.  
  - **DKIM**: enabled for used domain.  
  - **DMARC**: `p=quarantine` or `p=reject`, aligned with the From domain.  
- **External testing**: use Gmail or similar to confirm  
  `SPF=pass`, `DKIM=pass`, `DMARC=pass`.

---

## Hosting & Runtime

- **PHP**: 8.1+ (8.2 recommended).  
  - Extensions: none special; `mbstring` useful for Unicode validation.  
  - Must support `mail()` (sendmail or compatible MTA).  
- **Web server**: Apache or Nginx.  
  - HTTPS required on both front-end and service domain.  
- **CORS**: backend restricted to your site origins.  
- **Front-end (Publii or static site)**:  
  - Form posts to `https://service.domain.tlc/contact.php`.  
  - Required field names:  
    - `fullname`, `email`, `subject`, `message`.  
    - Optional: `phone`.  
    - Hidden: `website` (honeypot), `form_key`, `site_tag`, `render_ts`.  

---

## Security & Abuse Protection

- Honeypot field (hidden).  
- Static token (`form_key`).  
- Render time-trap (`render_ts`, ≥2s).  
- Rate limiting (default: 10 submissions per 30 minutes per IP).  
- CORS restrictions (only production domains).  
- HTTPS enforced.  

---

## PHP `mail()` (sendmail) vs PHPMailer

### `mail()` (sendmail)
- **Pros**
  - Built-in, no dependencies.  
  - Works on shared hosts.  
  - With `-f` sender and DKIM at host level → DMARC passes.  
- **Cons**
  - Minimal error reporting.  
  - Harder to send HTML or attachments.  
  - Deliverability tied to host’s IP reputation.  

### PHPMailer (SMTP)
- **Pros**
  - Full SMTP control (TLS, auth, ports, debug).  
  - Easy HTML + attachments.  
  - Can use dedicated relay (SES, Mailgun, etc.) for better deliverability.  
- **Cons**
  - Needs PHPMailer library installed.  
  - Requires credentials and more setup.  
  - Sometimes blocked on shared hosts.  

**Bottom line:** for a plain-text contact form on shared hosting, `mail()` is fine if SPF/DKIM/DMARC are correct. Switch to PHPMailer if you need HTML, attachments, or better diagnostics.

---

## Recommended Versions & Settings

- **PHP**: 8.2 (8.1 minimum).  
- **Web server**: Apache/Nginx current LTS with TLS 1.2+.  
- **TLS**: Let’s Encrypt or equivalent.  
- **Charset**: UTF-8 throughout.  
- **mbstring**: enabled.  

---

## Host Compatibility

| Environment                        | Works? | Notes                                                                 |
|-----------------------------------|--------|----------------------------------------------------------------------|
| **Shared host (DirectAdmin/cPanel)** | ✅      | Easiest setup with `mail()`. Ensure SPF/DKIM/DMARC are correct.       |
| **VPS (own Postfix/Exim)**        | ✅✅    | Full control. Add firewall/abuse protection.                          |
| **Static hosting (Netlify/Vercel)** | ✅      | Works for front-end, but backend must run elsewhere.                  |
| **Serverless functions**          | ⚠️      | Possible, but outbound email often requires external SMTP/API.        |

---

## Limitations

- **Daily mail caps**: host may limit messages (e.g. ~50/day).  
- **No logging**: current handler does not store submissions (privacy-friendly).  
- **Error UX**: JSON error codes only; no client-friendly messages yet.  
- **Spam resistance**: relies on honeypot + time-trap + rate limit.  
- **International input**:  
  - Name/Subject: extended Latin allowed (å/ä/ö/ø/æ/é), emojis blocked.  
  - Phone: digits + `+ - ( ) /`, 6–32 chars.  
- **CORS origins**: must be updated if adding staging/test domains.  
- **Token rotation**: `form_key` should be rotated periodically.  
- **No attachments**: plain-text mail only.

---

## Future Enhancements

- Switchable PHPMailer/SMTP transport.  
- Redirect on success (`/thanks.html`) instead of raw JSON.
- Error management pages instead of raw JSON
- Linked JS for client-side error mapping and live counters.  
- Server-side logging (file or DB, GDPR-aware).  
- Monitoring/alerts if `mail()` failures repeat.  
