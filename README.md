<img src="https://github.com/Ainz/gjallarform/blob/main/assets/gjallarfrom-large.png" alt="Gjallarform logo" title="Gjallarform Logo" width="300" align="right" />
# Gjallarform

**A minimal, secure contact form for PHP shared hosting**

Gjallarform is a self-contained contact form solution designed for simplicity, security, and ease of deployment. No dependencies, no frameworks, no database—just PHP's native `mail()` function and clean defensive coding.

Perfect for personal sites, portfolios, and small business pages running on shared hosting.

---

## Table of Contents

- [Name Origin](#name-origin)
- [Why Gjallarform?](#why-gjallarform)
- [Start configuration](#start-configuration)
  - [Prerequisites](#1-prerequisites)
  - [Installation](#2-installation)
  - [Test It](#3-test-it)
- [Spam Defense Features](#spam-defense-features)
  - [Honeypot](#honeypot-always-active)
  - [Time Trap](#time-trap-optional)
  - [Math Challenge](#math-challenge-optional)
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
- Layered spam defenses (honeypot, time trap, math challenge)
- Honeypot bot filtering (primary defense)
- Optional time trap for automated submissions
- Optional math challenge for manual spam operators
- Email header injection protection

**Maintainable by Design**

- All configuration in one contained $CFG array — no hunting through code
- Four files, self-contained — drop them in and they work
- CSS bundled into the JavaScript — one fewer file to manage
- Commented throughout — no guessing what a setting does

---

## Start configuration

### 1. Prerequisites

**Server Requirements**
- PHP 8.1 or higher
- PHP `mail()` function enabled *(Most shared hosting providers enable mail() by default. Check your control panel under PHP settings or Email, or contact your host if unsure. You can also verify by creating a one-line PHP file: `<?php phpinfo(); ?>` — search for "sendmail" in the output.)*
- FTP access to the server or a file manager in your hosting control panel from which you can handle files

:warning: **Mail Server Setup  (Critical)**
Before deploying, configure your mail server:
- **SPF records** for your domain ( <a href="https://en.wikipedia.org/wiki/Sender_Policy_Framework" target="_blank" rel="noopener noreferrer">what's SPF?</a> )
- **DKIM signing** enabled ( <a href="https://en.wikipedia.org/wiki/DomainKeys_Identified_Mail" target="_blank" rel="noopener noreferrer">what's DKIM?</a> )
- Configure a sending limit (recommended: 50 emails/day minimum)
- Create a dedicated email address for form submissions (e.g., `form-engine@yourdomain.com`)

> **SPF/DKIM in plain English:** These are email authentication methods that prove your server is allowed to send mail on behalf of your domain. Without them, Gmail and other providers will likely reject or spam-folder your form emails. Most hosting control panels have a section for configuring these under "Email" or "DNS" settings.

**Why mail limits matter:** Even with bot protection, a determined attacker could spam your inbox. Server-side limits are your ultimate safety net.

### 2. Installation

**Upload Files**

The `gjallarform/` directory is the self-contained deployable package — upload it as-is to your site root:

```
your-site/
└── gjallarform/
    ├── gjallarform.php
    ├── gjallarform.js
    ├── contact.html
    └── thankyou.html
```

**Configure gjallarform.php**

Open `gjallarform/gjallarform.php` and edit only the `$CFG` array:

```php
$CFG = [
  // Kill switch — set to false before going live
  'form_disabled' => true,

  // Mail routing
  'to'          => 'you@yourdomain.com',        // Where submissions go
  'from'        => 'form-engine@yourdomain.com', // MUST be on your domain, can be same as 'to'
  'fromDisplay' => 'Your Site Contact Form',     // Display name in From: header
  'replyDisplay'=> 'Your Site',                  // Display name in Reply-To: header

  // Site details
  'siteName'    => 'Your Site',
  'siteUrl'     => 'https://www.yoursite.com',   // Leave '' to auto-detect from httpHost
  'httpHost'    => 'www.yoursite.com',            // Fallback host used only when siteUrl is blank
  'contactPage' => 'contact.html',              // Assuming all files are in the same default directory
  'thankYouPage'=> 'thankyou.html',             // Assuming all files are in the same default directory
  'timezone'    => 'Your/TimeZone',

  // Spam defense
  'formKey'         => 'yoursite-change-this-to-a-long-random-secret', // Fixed secret — must match hidden form_key field in HTML
  'timeTrapEnabled' => true,          // Reject submissions faster than timeTrapMinMs
  'timeTrapMinMs'   => 2000,
  'timeTrapGraceMs' => 50,
  'math_challenge'  => true,          // Enable arithmetic challenge question
];
```

**Update HTML Form**
In `gjallarform/contact.html`, change the form action:
```html
<form class="gjallarform" action="https://www.yoursite.com/gjallarform/gjallarform.php" method="POST">
```

Update the form key value to match your PHP config:
```html
<input name="form_key" type="hidden" value="yoursite-change-this-to-a-long-random-secret"> <!-- Must be the exact same fixed string as formKey in gjallarform.php -->
```

**Link the JavaScript**
```html
<script src="https://www.yoursite.com/gjallarform/gjallarform.js" defer></script>
```

### 3. Test It
1. Submit the form with valid data → should redirect to thank you page
2. Honeypot test — use browser dev tools to unhide and fill the `website` field, then submit → should redirect to thank you page (no email sent)
3. Submit too quickly → should bounce back with error (if time trap enabled)
4. Check your inbox for both admin notification and confirmation copy

---

## Spam Defense Features

Gjallarform uses layered spam defenses that you can enable or disable individually. All sites get the honeypot and validation baseline; the time trap and math challenge are opt-in.

### Honeypot (Always Active)

A hidden form field that legitimate users never see or fill out, but bots often do automatically. When triggered, the form appears to succeed but no email is sent. ( <a href="https://en.wikipedia.org/wiki/Honeypot_(computing)#Spam_versions" target="_blank" rel="noopener noreferrer">What's a honeypot?</a> )

Combined with server-side mail limits, the honeypot stops 95%+ of automated spam. Simple, effective, no false positives. Also always active: email validation, required field checks, and field length limits.

### Time Trap (Optional)

```php
'timeTrapEnabled' => true,   // Enable/disable
'timeTrapMinMs'   => 2000,   // Minimum time before submission (ms)
'timeTrapGraceMs' => 50,     // Jitter allowance (ms)
```

Measures how long between page load and form submission. Requires at least 2 seconds (configurable), which catches bots that fill forms instantly but doesn't affect legitimate users who need time to type. Gracefully degrades if JavaScript is disabled.

### Math Challenge (Optional)

```php
'math_challenge' => true,    // Enable/disable
```

Simple arithmetic question (e.g., "What is 5 + 3?") that must be answered correctly. Stops manual spam operators while remaining trivial for real users.

**How question selection works:** Gjallarform picks from a pool of 15 simple arithmetic questions. The selected question is derived from a hash of your `formKey` combined with your site's domain (`HTTP_HOST`), so:

- The same question always appears on your site — no random flickering on refresh
- Two sites that happen to share a similar `formKey` but run on different domains will land on different questions
- The question index is computed server-side and cannot be manipulated by the submitter

This means there is no config to tune — the seeding just works automatically once `formKey` is set.

### Recommended Combinations

| Use Case | Honeypot | Time Trap | Math Challenge |
|----------|----------|-----------|----------------|
| Personal blog, portfolio | Always on | Off | Off |
| Small business site | Always on | On | Off |
| Higher-traffic or spam-targeted site | Always on | On | On |

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
   - **Form key validation** (if enabled) - A token that proves the submission came from your actual form, not a forged request from another site ( <a href="https://owasp.org/www-community/attacks/csrf" target="_blank" rel="noopener noreferrer">CSRF protection</a> )
   - Time trap check (if enabled)
   - Field validation (presence, format, length)
   - Email composition (admin + confirmation)
   - Mail sending via PHP `mail()`

5. **Redirect**
   - **303 redirect to `thankyou.html`** - Uses the <a href="https://en.wikipedia.org/wiki/Post/Redirect/Get" target="_blank" rel="noopener noreferrer">Post/Redirect/Get pattern</a> (PRG), which prevents duplicate submissions if the user refreshes their browser. The 303 status code specifically tells browsers "don't resubmit the form on refresh."
   - sessionStorage populated with name and subject for a personalized thank-you message
   - JavaScript renders the thank-you details (name, subject)

6. **Email Delivery**
   - Admin receives notification with reference ID (authoritative ref appears in email only)
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

> [!WARNING]
> **`form_disabled` is set to `true` by default.**
> The form will silently reject all submissions until you change this.
> Open `gjallarform.php` and set `'form_disabled' => false` before going live.

### Kill Switch

| Setting | Default | Notes |
|---------|---------|-------|
| `form_disabled` | `true` | Set to `false` to accept live submissions. Shipped as `true` so the form is safe to deploy before configuration is complete. When `true`, every POST request returns a 503 and exits immediately — no email is sent, no error is shown to the visitor. |

### Mail Routing

| Setting | Required | Default | Notes |
|---------|----------|---------|-------|
| `to` | Yes | — | The address that receives submission notification emails. Can be any address (your own domain, Gmail, etc.). |
| `from` | Yes | — | Envelope sender. **Must be an address on your own domain** (e.g. `form-engine@yourdomain.com`) for SPF/DKIM to pass. This is not the address visitors reply to — see `to` for that. |
| `fromDisplay` | No | `''` | Display name shown in the `From:` header of outgoing emails (e.g. `YourSite Contact Form`). Purely cosmetic — has no effect on deliverability. |
| `replyDisplay` | No | `''` | Display name for the `Reply-To:` header in admin notification emails. Purely cosmetic. |

### Site Identity

| Setting | Required | Default | Notes |
|---------|----------|---------|-------|
| `siteName` | Yes | — | Used in email subjects and labels (e.g. produces subjects like `YourSite Contact`). |
| `siteUrl` | No | Auto-detected | Full base URL including scheme (e.g. `https://www.yoursite.com`). Used to build redirect URLs after submission. If blank, falls back to `httpHost` with an auto-detected scheme. Setting this explicitly is safer and recommended. |
| `httpHost` | No | Auto-detected | Fallback hostname (e.g. `www.yoursite.com`) used only when `siteUrl` is blank. Unlike `$_SERVER['HTTP_HOST']`, this value is never taken from the request — it must be set here explicitly, which prevents host-header injection. Has no effect when `siteUrl` is set. |
| `contactPage` | No | `contact.html` | Path to the contact form page relative to site root (e.g. `gjallarform/contact.html`). Used as the destination for error redirects. |
| `thankYouPage` | No | `thankyou.html` | Path to the thank-you page relative to site root (e.g. `gjallarform/thankyou.html`). Used as the destination for the success redirect. |
| `timezone` | No | `UTC` | PHP timezone string (e.g. `Europe/Rome`). Affects only the timestamp shown in notification emails. Has no effect on form behaviour or validation. |

### Spam Defense

| Setting | Default | Notes |
|---------|---------|-------|
| `formKey` | `''` (disabled) | A fixed secret string shared between this config and the hidden `form_key` field in `contact.html`. Submissions with a missing or mismatched key are rejected — this is the CSRF-like protection layer. Must be identical and static in both places; never generate it dynamically. Leave blank (`''`) to disable this check entirely. |
| `timeTrapEnabled` | `true` | Enables the time trap. When on, submissions arriving faster than `timeTrapMinMs` milliseconds after page load are rejected. Disable if users frequently use autofill or paste pre-typed messages and trigger false positives. |
| `timeTrapMinMs` | `2000` | Minimum milliseconds between page load and submission. 2000 ms (2 seconds) stops bots that submit instantly. Raise to 3000–5000 if you see false positives from fast autofill users. |
| `timeTrapGraceMs` | `50` | Jitter allowance added to `timeTrapMinMs` to absorb minor clock differences between browser and server. Rarely needs changing. |
| `math_challenge` | `true` | Enables the arithmetic math challenge. The question is drawn from a pool of 15 and is seeded from `formKey` + your domain, so the same question always appears on your site but differs across installations. Disable if you prefer a frictionless form and rely on the honeypot and time trap alone. |

---

## Customization

### Form Fields

#### Contact Page

**User-facing fields:**
| Field | Required | Notes |
|-------|----------|-------|
| `fullname` (or `name`) | Yes | Accepts either field name |
| `email` | Yes | ASCII only, validated format |
| `subject` | No | Defaults to site name if omitted |
| `message` | Yes | |
| `math_answer` | When enabled | Answer to math challenge question |

**Hidden fields — do not remove:**
| Field | Set by | Purpose |
|-------|--------|---------|
| `website` | User (honeypot) | Must stay hidden via CSS — bots fill it, humans don't |
| `form_key` | You | Must match PHP config |
| `site_tag` | You | Informational label |
| `render_ts` | JavaScript | Page load timestamp for time trap |
| `math_index` | JavaScript | Math question index |

#### Thank You Page

These element IDs are populated automatically by JavaScript after a successful submission:

| Element ID | Content |
|------------|---------|
| `gjallarform-thankyou-root` | Required — triggers rendering |
| `gjallarform-thankyou-details` | Container, set `hidden` by default |
| `gjallarform-thankyou-name` | Submitter's first name |
| `gjallarform-thankyou-subject` | Message subject |

#### Adding Fields
You'll need to modify:
1. HTML form markup
2. PHP input reading (`$_POST`)
3. PHP validation logic
4. Email body composition

#### Removing Fields
- `subject` can be removed (will default to site name if omitted)
- Don't remove `fullname`, `email`, or `message` without adjusting PHP validation
- Don't remove any hidden fields without understanding their role above

### Page Filenames

The default configuration expects `contact.html` and `thankyou.html` inside the `gjallarform/` directory. You can change these paths in the `$CFG` array if you move or rename the files:

```php
$CFG = [
  // ...
  'contactPage'  => 'gjallarform/contact.html',   // path relative to site root
  'thankYouPage' => 'gjallarform/thankyou.html',
  // ...
];
```

**Examples:**
- `'gjallarform/contact.html'` → redirects to `https://yoursite.com/gjallarform/contact.html`
- `'contact.html'` → redirects to `https://yoursite.com/contact.html` (if moved to root)
- `'contact'` → redirects to `https://yoursite.com/contact` (web server handles trailing slash)

The leading slash is handled automatically.

**If your site uses directory-based URLs** (e.g. `/contact/` instead of `/contact.html`),
use just the directory name without slashes:

```php
'contactPage'  => 'contact',   // → https://yoursite.com/contact/
'thankYouPage' => 'thankyou',  // → https://yoursite.com/thankyou/
```

The trailing slash is handled automatically by your web server.

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

**Option 3:** Disable the time trap
```php
'timeTrapEnabled' => false,  // Disable time trap entirely
```

### JavaScript Not Loading

1. Verify script path is correct
2. Check browser console for errors
3. Ensure `defer` attribute is present
4. Check Content Security Policy (CSP) if strict

### Form Redirects to Wrong Page

1. Verify `$CFG['siteUrl']` is set correctly
2. Check that `gjallarform/thankyou.html` (or your configured `thankYouPage` path) exists
3. Check `.htaccess` rules (if using Apache)

---

## Security Notes

### What Gjallarform Protects Against

✅ Most automated bots (honeypot)  
✅ Fast form scrapers (time trap)  
✅ Email header injection (sanitization of subject and all display-name fields)  
✅ CSRF-style attacks (form key)  
✅ Timing attacks on form key ( constant-time comparison via <a href="https://www.php.net/manual/en/function.hash-equals.php" target="_blank" rel="noopener noreferrer">`hash_equals()`</a> )  
✅ Math challenge question-picking (index computed server-side from `formKey` + domain — cannot be manipulated by submitter, differs across installations)  
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

- [ ] **`form_disabled` set to `false`** (ships as `true` — form rejects all submissions until changed)
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

See [CHANGELOG.md](CHANGELOG.md) for version history.

**Looking Forward:**
- **Defense tiers** — a single `defenseLevel` setting (`basic`/`standard`/`strict`) that auto-configures spam defense features as a group
- **Rate limiting** — IP-based throttling for high-traffic sites (requires file/cache storage)
- **Form-agnostic pattern matching** — work with any form structure
- **Internationalization** — localized error messages and email templates

**Under Consideration:**
- Database logging option
- AJAX submission mode
- Multi-form support

---

*"A little contact form that does exactly what it needs to, and nothing more."*
