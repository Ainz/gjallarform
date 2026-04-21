<img width="800" height="" alt="image" src="https://github.com/user-attachments/assets/39001114-08cf-4540-9fb6-705be251a8d2" />

# Gjallarform

**A minimal, secure contact form for PHP shared hosting**

Gjallarform is a self-contained contact form solution designed for simplicity, security, and ease of deployment. No dependencies, no frameworks, no database—just PHP's native `mail()` function and clean defensive coding.

Perfect for personal sites, portfolios, and small business pages running on shared hosting.

---

## Table of Contents

- [Name Origin](#name-origin)
- [Why Gjallarform?](#why-gjallarform)
- [Quick Start](#quick-start)
  - [Prerequisites](#1-prerequisites)
  - [Installation](#2-installation)
  - [Test It](#3-test-it)
- [Defense Tiers Explained](#defense-tiers-explained)
  - [Basic](#basic-recommended-for-most-users)
  - [Standard](#standard-default)
  - [Strict](#strict-future)
- [Form Flow](#form-flow)
- [Configuration Reference](#configuration-reference)
- [Customization](#customization)
- [Troubleshooting](#troubleshooting)
- [Security Notes](#security-notes)
- [GDPR and Privacy Compliance](#gdpr-and-privacy-compliance)
- [Deployment Checklist](#deployment-checklist)
- [License](#license)
- [Support & Contribution](#support--contribution)
- [Roadmap](#roadmap)

---

## Name Origin

**Gjallarform** combines "Gjallar-" (from Gjallarhorn, the horn blown by the god Heimdall in Norse mythology to signal important events) with "form" (contact form). Just as the Gjallarhorn alerts the gods, Gjallarform alerts you to incoming messages.

---

## Why Gjallarform?

**Built for Real Constraints**
- Works on basic shared hosting (no special PHP extensions)
- No external services or API keys required
- No database needed
- Minimal server resources

**Security Without Complexity**
- Tiered defense system (choose your protection level)
- Honeypot bot filtering (primary defense)
- Optional time trap for automated submissions
- Optional math challenge for manual spam operators
- Email header injection protection
- Graceful degradation for edge cases

**Maintainable by Design**
- All configuration in one place
- Clean separation of concerns
- Well-commented code
- Designed for "future you in 6 months"

---

## Quick Start

### 1. Prerequisites

**Server Requirements**
- PHP 8.1 or higher
- PHP `mail()` function enabled
- Write access to upload files

:warning: **Mail Server Setup  (Critical)**
Before deploying, configure your mail server:
- **SPF records** for your domain (<a href="https://en.wikipedia.org/wiki/Sender_Policy_Framework" target="_blank" rel="noopener noreferrer">what's SPF?</a>)
- **DKIM signing** enabled (<a href="https://en.wikipedia.org/wiki/DomainKeys_Identified_Mail" target="_blank" rel="noopener noreferrer">what's DKIM?</a>)
- Configure a sending limit (recommended: 50 emails/day minimum)
- Create a dedicated email address for form submissions (e.g., `form-engine@yourdomain.com`)

> **SPF/DKIM in plain English:** These are email authentication methods that prove your server is allowed to send mail on behalf of your domain. Without them, Gmail and other providers will likely reject or spam-folder your form emails. Most hosting control panels have a section for configuring these under "Email" or "DNS" settings.

**Why mail limits matter:** Even with bot protection, a determined attacker could spam your inbox. Server-side limits are your ultimate safety net.

### 2. Installation

**Upload Files**
```
your-site/
├── gjallarform/
│   ├── gjallarform.php
│   └── gjallarform.js
├── contact.html
└── thankyou.html
```

**Configure gjallarform.php**
Open `gjallarform/gjallarform.php` and edit only the `$CFG` array:

```php
$CFG = [
  // Mail routing
  'to'          => 'you@yourdomain.com',        // Where submissions go
  'from'        => 'form-engine@yourdomain.com', // MUST be on your domain, can be same as 'to'
  'fromDisplay' => 'Your Site Contact Form',
  'replyDisplay'=> 'Your Site',

  // Site details
  'siteName'     => 'Your Site',
  'siteUrl'      => 'https://www.yoursite.com',
  'contactPage'  => 'contact.html',    // Customizable contact form filename
  'thankYouPage' => 'thankyou.html',   // Customizable thank you page filename
  'timezone'     => 'Europe/Stockholm',

  // Defense level
  'defenseLevel'    => 'standard',  // 'basic', 'standard', or 'strict'
  'formKey'         => 'yoursite-' . rand(100000, 999999),
  'timeTrapMinMs'   => 2000,
  'timeTrapGraceMs' => 50,
  'math_challenge'  => true,        // Enable simple math verification question
];
```

**Update HTML Form**
In `contact.html`, change the form action:
```html
<form class="gjallarform" action="https://www.yoursite.com/gjallarform/gjallarform.php" method="POST">
```

Update the form key value to match your PHP config:
```html
<input name="form_key" type="hidden" value="yoursite-123456">
```

**Link the JavaScript**
```html
<script src="https://www.yoursite.com/gjallarform/gjallarform.js" defer></script>
```

### 3. Test It

1. Submit the form with valid data → should redirect to thankyou.html page
2. Fill the hidden "website" field → should redirect to thankyou.html (no email sent)
3. Submit too quickly → should bounce back with error (if time trap enabled)
4. Check your inbox for both admin notification and confirmation copy

---

## Defense Tiers Explained

Choose your protection level based on your site's traffic and risk profile.

### Basic (Recommended for Most Users)
```php
'defenseLevel' => 'basic',
```

**What you get:**
- **Honeypot field** (<a href="https://en.wikipedia.org/wiki/Honeypot_(computing)#Spam_versions" target="_blank" rel="noopener noreferrer">what's a honeypot?</a>) - A hidden form field that legitimate users never see or fill out, but bots often do automatically. When triggered, the form appears to succeed but no email is sent.
- Email validation
- Required field checks

**Best for:**
- Personal blogs
- Portfolio sites
- Low-traffic pages
- Sites with mail server limits in place

**Why it's enough:** Combined with server-side mail limits, the honeypot stops 95%+ of automated spam. Simple, effective, no false positives.

### Standard (Default)
```php
'defenseLevel' => 'standard',
```

**Adds to Basic:**
- **Time trap** - Measures how long between page load and form submission. Requires at least 2 seconds (configurable), which catches bots that fill forms instantly but doesn't affect legitimate users who need time to type.
- **Math challenge** - Simple arithmetic question (e.g., "What is 5 + 3?") that must be answered correctly. Stops manual spam operators while remaining trivial for real users. Question selection is deterministic based on form key for consistency.
- Gracefully degrades if JavaScript disabled

**Best for:**
- Small business sites
- Sites expecting moderate traffic
- When you want an extra layer without complexity
- Protection against both automated bots and manual spam operators

**Trade-off:** Adds minimal friction for legitimate users, stops bots that bypass the honeypot and manual spam operators.

### Strict (Future) — Not currently implemented
```php
'defenseLevel' => 'strict',
```

**Will add:**
- Rate limiting by IP address
- Requires file/cache storage

**Status:** Planned for later version. Not implemented in current release.

---

## Form Flow

Understanding how Gjallarform works helps with troubleshooting and customization.

### User Journey (Success Path)

1. **Page Load**
   - HTML renders form with honeypot field (hidden via CSS)
   - Inline script sets `render_ts` timestamp immediately
   - JavaScript loads, injects CSS, attaches validation helpers

2. **User Fills Form**
   - Native browser validation provides real-time feedback
   - Draft automatically cached in sessionStorage on input
   - Email field validates ASCII-only characters

3. **Submit**
   - JavaScript validates form before submission
   - Form POSTs to `gjallarform/gjallarform.php`
   - PHP validates all inputs server-side

4. **PHP Processing**
   - Honeypot check (silent success if triggered)
   - **Form key validation** (if enabled) - A token that proves the submission came from your actual form, not a forged request from another site (<a href="https://owasp.org/www-community/attacks/csrf" target="_blank" rel="noopener noreferrer">CSRF protection</a>)
   - Time trap check (if standard/strict tier)
   - Field validation (presence, format, length)
   - Email composition (admin + confirmation)
   - Mail sending via PHP `mail()`

5. **Redirect**
   - **303 redirect to `thankyou.html`** - Uses the <a href="https://en.wikipedia.org/wiki/Post/Redirect/Get" target="_blank" rel="noopener noreferrer">Post/Redirect/Get pattern</a> (PRG), which prevents duplicate submissions if the user refreshes their browser. The 303 status code specifically tells browsers "don't resubmit the form on refresh."
   - sessionStorage populated with submission details
   - JavaScript renders personalized thank-you message

6. **Email Delivery**
   - Admin receives notification with reference ID
   - User receives confirmation copy (best-effort)
   - Both emails use aligned envelope sender for deliverability

### Error Handling

**Validation Errors**
- PHP redirects back to form with `?err=code`
- JavaScript detects error code, restores draft from sessionStorage
- Native browser validation shows appropriate field-level error

**Mail Failures**
- Logged to PHP error log (check your server logs)
- User sees generic "send failed" message
- No sensitive details exposed

**Bot Detection**
- Honeypot triggers → silent success (looks successful, no email sent)
- Time trap triggers → error message, form bounce
- Form key mismatch → validation error

---

## Configuration Reference

### Required Settings

| Setting | Purpose | Example |
|---------|---------|---------|
| `to` | Where submissions are sent | `admin@yoursite.com` |
| `from` | Envelope sender (must be on your domain) | `form@yoursite.com` |
| `siteName` | Used in email subjects | `My Site` |

### Optional Settings

| Setting | Default | Purpose |
|---------|---------|---------|
| `siteUrl` | Auto-detected | Base URL for redirects |
| `contactPage` | `contact.html` | Contact form page filename/path |
| `thankYouPage` | `thankyou.html` | Thank you page filename/path |
| `timezone` | `UTC` | Timezone for timestamps |
| `formKey` | `''` (disabled) | CSRF-like token |
| `defenseLevel` | `standard` | `basic`/`standard`/`strict` |
| `timeTrapMinMs` | `2000` | Minimum submit time (ms) |
| `timeTrapGraceMs` | `50` | Jitter allowance (ms) |
| `math_challenge` | `true` | Enable math verification question |

### Email Addresses Explained

**`from` Address**
- **MUST** be on your domain for SPF/DKIM to work
- Used as envelope sender (`-f` flag)
- Doesn't need to be a real mailbox (but can be)
- Example: `noreply@yourdomain.com` or `form-engine@yourdomain.com`

**`to` Address**
- Where you actually want to receive submissions
- Can be on any domain (Gmail, etc.)
- Used as `Reply-To:` in confirmation emails
- Example: `you@gmail.com` or `contact@yourdomain.com`

**Why this matters:** Mail servers check SPF/DKIM against the envelope sender, not the recipient. Using a `from` address on your domain significantly improves deliverability.

---

## Customization

### Form Fields

The current implementation expects these fields:
- `fullname` (or `name`) - Required
- `email` - Required, validated
- `subject` - Optional (defaults to site name)
- `message` - Required
- `website` - Honeypot (must remain hidden)

**Adding Fields:**
You'll need to modify:
1. HTML form markup
2. PHP input reading (`$_POST`)
3. PHP validation logic
4. Email body composition

**Removing Fields:**
- `subject` can be removed (will default to site name)
- Don't remove `fullname`, `email`, or `message` without adjusting validation

### Page Filenames

By default, Gjallarform expects `contact.html` and `thankyou.html` at your site root. You can customize these filenames or use subdirectories by editing the `$CFG` array:

```php
$CFG = [
  // ...
  'contactPage'  => 'forms/contact.html',    // Use subdirectory
  'thankYouPage' => 'forms/success.html',    // Custom filename
  // ...
];
```

**Examples:**
- `'contact.html'` → redirects to `https://yoursite.com/contact.html`
- `'forms/contact.html'` → redirects to `https://yoursite.com/forms/contact.html`
- `'get-in-touch.html'` → redirects to `https://yoursite.com/get-in-touch.html`

The leading slash is handled automatically, so you can use either `contact.html` or `/contact.html`.

### Styling

CSS is injected by `gjallarform.js`. To customize:

**Option 1:** Override in your site's CSS
```css
.gjallarform input,
.gjallarform textarea {
  border-color: your-color;
  /* etc */
}
```

**Option 2:** Modify the CSS string in `gjallarform.js`
```javascript
var CSS = `
  /* Your custom styles here */
`;
```

### Error Messages

Error codes are passed via URL: `?err=code`

Current codes:
- `validation` - Form key mismatch
- `too_fast` - Time trap triggered
- `math_wrong` - Math challenge answer incorrect
- `math_invalid` - Math challenge answer invalid format
- `name_missing` - Name field empty
- `email_missing` - Email field empty
- `message_missing` - Message field empty
- `name_too_long` - Name exceeds 200 characters
- `email_too_long` - Email exceeds 254 characters
- `subject_too_long` - Subject exceeds 300 characters
- `message_too_long` - Message exceeds 10,000 characters
- `email_ascii_only` - Non-ASCII characters in email
- `email_invalid` - Email format invalid
- `send_failed` - Mail delivery failed

Custom error handling in JavaScript (see `hasErrCode()` function in `gjallarform.js`).

---

## Troubleshooting

### "Mail send failed" Error

**Check PHP error logs first.** Common causes:

1. **PHP mail() not configured**
   - Verify with: `php -i | grep sendmail`
   - Contact your hosting provider

2. **SPF/DKIM not set up**
   - Mail servers reject unauthenticated mail
   - Add SPF record for your domain
   - Enable DKIM in your hosting control panel

3. **From address not on domain**
   - Change `'from'` to use `@yourdomain.com`

4. **Mail server limits exceeded**
   - Check your hosting quota
   - Add rate limiting if needed

### Form Submits But No Email Arrives

1. **Check spam folder** (both admin and confirmation)
2. **Verify `$CFG['to']` address** is correct
3. **Look in PHP error logs** for sendmail errors
4. **Test with a different email provider** (some providers block automated mail)

### Time Trap False Positives

If legitimate users are getting "too fast" errors:

**Option 1:** Increase the threshold
```php
'timeTrapMinMs' => 3000,  // 3 seconds instead of 2
```

**Option 2:** Add more grace time
```php
'timeTrapGraceMs' => 200,  // More lenient
```

**Option 3:** Switch to basic tier
```php
'defenseLevel' => 'basic',  // Disable time trap entirely
```

### JavaScript Not Loading

1. Verify script path is correct
2. Check browser console for errors
3. Ensure `defer` attribute is present
4. Check Content Security Policy (CSP) if strict

### Form Redirects to Wrong Page

1. Verify `$CFG['siteUrl']` is set correctly
2. Check that `thankyou.html` exists at root
3. Check `.htaccess` rules (if using Apache)

---

## Security Notes

### What Gjallarform Protects Against

✅ Most automated bots (honeypot)  
✅ Fast form scrapers (time trap)  
✅ Email header injection (sanitization of subject and all display-name fields)  
✅ CSRF-style attacks (form key)  
✅ Timing attacks on form key (constant-time comparison via <a href="https://www.php.net/manual/en/function.hash-equals.php" target="_blank" rel="noopener noreferrer">`hash_equals()`</a>)  
✅ Math challenge question-picking (index computed server-side when `formKey` is set)  
✅ Oversized payloads (server-side field length limits)  

### What It Doesn't Protect Against

❌ Sophisticated bots with JavaScript execution  
❌ Distributed attacks from multiple IPs (without rate limiting)  
❌ Determined human spammers  
❌ DDoS attacks (use server-level protection)  

### Best Practices

1. **Set mail server limits** - This is your ultimate safety net
2. **Monitor your inbox** - First few days after deployment
3. **Check error logs regularly** - Catch issues early
4. **Keep PHP updated** - Security patches matter
5. **Use HTTPS** - Protects form data in transit
6. **Review submissions** - Watch for patterns if spam increases

### Privacy Considerations

Gjallarform logs IP addresses by default. Ensure you:
- Disclose this in your form (✅ already done in example HTML)
- Comply with GDPR/privacy laws in your jurisdiction
- Have a privacy policy that mentions form submissions

To disable IP logging, remove this line from `gjallarform/gjallarform.php`:
```php
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
```

---

## GDPR and Privacy Compliance

Gjallarform is designed for GDPR compliance when properly configured:

**What Gjallarform Does:**
- ✅ Processes only data the user explicitly provides
- ✅ Doesn't use cookies (sessionStorage is local-only, not transmitted)
- ✅ Includes IP disclosure notice in example form
- ✅ No third-party data sharing
- ✅ No persistent storage (emails only)

**Your Responsibilities:**
1. Add a privacy policy explaining:
   - What data you collect (name, email, message, IP)
   - Why you collect it (to respond to inquiries)
   - How long you keep it (recommend: delete after resolution)
   - User rights (access, deletion requests)

2. Consider adding consent checkboxes if required in your jurisdiction:
   ```html
   <label>
     <input type="checkbox" name="consent" required>
     I agree to the <a href="/privacy">privacy policy</a>
   </label>
   ```

3. Honor deletion requests by removing emails from your inbox/archive

**Note:** Gjallarform doesn't log to database, so there's no persistent storage to manage beyond your email inbox.

---

## Deployment Checklist

Before going live:

- [ ] PHP 8.1+ confirmed
- [ ] Mail server configured (SPF, DKIM)
- [ ] Mail sending limits set
- [ ] `$CFG` array filled out
- [ ] Form action URL updated
- [ ] Form key matches between HTML and PHP
- [ ] Script path correct in HTML
- [ ] Test submission received in inbox
- [ ] Confirmation email received
- [ ] Honeypot test (fill hidden field → no email sent)
- [ ] Error handling test (submit empty form)
- [ ] Mobile responsiveness checked
- [ ] Privacy disclosure visible on form

---

## License

Gjallarform is free software licensed under the GNU General Public License v3.0 or later.

© 2025–present Conram.it  
SPDX-License-Identifier: GPL-3.0-or-later

See [LICENSE](LICENSE) file for full terms.

You are free to:
- Use Gjallarform for any purpose
- Study and modify the source code
- Distribute original or modified versions

Under the condition that derivative works are also licensed under GPL-3.0-or-later.

---

## Support & Contribution

This is a personal project released for public use. 

**Questions?** Open an issue on GitHub.  
**Found a bug?** Pull requests welcome.  
**Need commercial support?** Contact via the repository.

---

## Roadmap

See [CHANGELOG.md](CHANGELOG.md) for planned features.

**Upcoming:**
- Form-agnostic pattern matching (1.x)
- Rate limiting module (strict tier)
- Internationalization support

**Under Consideration:**
- Database logging option
- AJAX submission mode
- Multi-form support

---

*"A little contact form that does exactly what it needs to, and nothing more."*
