# Form Flow

This document describes the flow of a form submission.

---
Form Flow applies to v1.1.0
---

## 1. Frontend
- A static site form (e.g. Publii, Hugo, plain HTML) posts to `https://service.domain.tld/contact.php`.
- Hidden fields included:
  - `form_key` → anti-CSRF token (rotated periodically).
  - `site_tag` → appears in email subject (helps identify the site).
  - `render_ts` → timestamp set on render (used as a time-trap).
  - `website` → honeypot field (should remain empty).

---

## 2. Backend Validation
1. **Origin check (CORS)** → only accepts known domains (e.g. `https://www.domain.tld`).  
2. **Rate limiting** → rejects if >10 requests per 30 minutes per IP.  
3. **Time-trap** → rejects if submitted too quickly (<2s).  
4. **Honeypot** → rejects if `website` field filled.  
5. **Field validation**:
   - `fullname` & `subject`: extended Latin allowed, emojis rejected.
   - `email`: RFC-style filter.
   - `phone`: optional; accepts digits and `+ - ( ) /`.
   - `message`: max 4000 chars, control chars stripped.

---

## 3. Mail Delivery
- Sent via PHP `mail()` (sendmail).  
- From: `form-engine@domain.tld`.  
- To: designated recipient (e.g. `recipient@domain.tld`).  
- Subject: `[site_tag] New message`.  
- Headers: UTF-8 charset, Reply-To set to sender email.  

---

## 4. Responses
- Success → `{ "ok": true }`  
- Failure → `{ "ok": false, "error": "reason" }`

---

## 5. Debug Flow
- Using `contact-echo.php` instead of `contact.php` will return a JSON dump of the POST payload for testing.
