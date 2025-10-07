# Prerequisites & Limitations

This document lists what is required for running the Contact Form Engine, and outlines known limitations.

---

# Template Variables
- **service.domain.tld** → replace with backend service domain.  
- **www.domain.tld** → replace with frontend website domain.  
- **form-engine@domain.tld** → replace with dedicated sender mailbox.  
- **recipient@domain.tld** → replace with delivery mailbox.  
- **site_tag** → identifier string for subject line.  
- **form_key** → anti-CSRF token version.

---

## Domain & Email
- Dedicated sender mailbox: `form-engine@domain.tld`.  
- SPF, DKIM, and DMARC is a highly recommended configuration for sending domain.  
- Confirm deliverability by testing with external mailbox (e.g. Gmail).

---

## Hosting & Runtime
- **PHP**: 8.1+ (8.2 recommended).  
- **Mail transport**: PHP `mail()` (sendmail).  
- **Web server**: Apache or Nginx with HTTPS. (Host proivder.)
- **Static site frontend** posts to `https://service.domain.tld/contact.php`.

---

## Security & Abuse Protection
- CORS restrictions.  
- Honeypot hidden field.  
- CSRF token.  
- Render time-trap (`render_ts`).  
- Rate limiting (10 submissions per 30 minutes per IP).  
- No attachments, plain-text only.

---

## Limitations
- Shared hosting may cap messages (e.g. ~50/day).  
- No persistent logging by default (privacy-friendly).  
- Error handling is JSON only.  
- Spam resistance: honeypot + time-trap + rate limiting.  
- International characters supported (å/ä/ö/ø/æ/é). Emojis blocked.  

---

## Roadmap
- Optional PHPMailer/SMTP transport.  
- Client-side error messages.  
- Linked JS counters via `data-count`.  
- Optional logging for auditing.

