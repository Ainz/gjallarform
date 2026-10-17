# Gjallarform

<img src="assets/gjallarform-large.png" alt="Gjallarform logo" width="300" align="right">

**A small, self-hosted contact form for PHP shared hosting.**

Gjallarform is intended for personal sites, portfolios, and small organizations that need a contact form without a framework, database, hosted form service, or recurring fee. It uses PHP's native `mail()` function and a small JavaScript client.

Version 0.96 is beta software. Review the configuration, test on your own hosting, and keep your hosting provider's outbound-mail limits enabled.

[Introduction and project background](https://www.conram.it/soholab/gjallarform-intro.html) · [Releases](https://github.com/Ainz/gjallarform/releases) · [Issues](https://github.com/Ainz/gjallarform/issues) · [Discussions](https://github.com/Ainz/gjallarform/discussions)

![License](https://img.shields.io/badge/License-GPL%20v3-blue)
![Status](https://img.shields.io/badge/Status-Public%20Beta-yellow)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)

## What it provides

- Four deployable files in `gjallarform/`
- No application database or package manager
- Server-signed, short-lived form challenges
- Honeypot, timing check, arithmetic challenge, and per-IP rate limiting
- Server-side field and email validation
- Header-injection protection and aligned envelope sender
- Draft recovery after a validation redirect
- Optional confirmation copies, disabled by default
- Automated checks on PHP 8.2 through 8.5

These layers reduce ordinary automated abuse; they do not make a public endpoint immune to sophisticated bots, distributed attacks, or denial-of-service attacks.

## Requirements

- PHP 8.2 or newer; PHP 8.3 or newer is recommended
- PHP `mail()` enabled and configured by the host
- HTTPS
- JavaScript enabled in the visitor's browser
- A writable PHP system temporary directory, or another non-public writable directory for rate-limit state
- Correct SPF and DKIM records for the sender domain

## Quick start

1. Download a release archive and upload the `gjallarform/` directory to the site.
2. Open `gjallarform/gjallarform.php` and edit the `$CFG` block.
3. Generate a unique signing secret on a machine with PHP:

   ```sh
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

4. Paste that value into `formSecret`. It belongs only in PHP—never in HTML or JavaScript.
5. Set `to`, `from`, `siteName`, `siteUrl`, page paths, and timezone.
6. Update the form action and script path in `contact.html` if the files are not at `/gjallarform/`.
7. Leave `sendConfirmation` off for the first test cycle.
8. Set `form_disabled` to `false` only after configuration is complete.
9. Run the [deployment tests](#deployment-tests) before linking the form publicly.

The deployable directory is:

```text
gjallarform/
├── contact.html
├── gjallarform.js
├── gjallarform.php
└── thankyou.html
```

The template uses:

```html
<form class="gjallarform" action="/gjallarform/gjallarform.php" method="post" novalidate>
```

JavaScript requests a signed challenge from that same URL. Keep the form page, script, and PHP handler on the same origin unless you deliberately add and review a CORS policy.

## Configuration

The shipped form is disabled and contains placeholder addresses and a placeholder secret. It will not operate until these are replaced.

### Mail and identity

| Setting | Default | Purpose |
|---|---:|---|
| `form_disabled` | `true` | Emergency switch. Change to `false` only after testing the configuration. |
| `to` | placeholder | Mailbox that receives contact messages. |
| `from` | placeholder | Envelope and `From` address. Use an address on the sending domain. |
| `fromDisplay` | placeholder | Display name on outgoing mail. |
| `replyDisplay` | placeholder | Display name used by optional confirmation copies. |
| `sendConfirmation` | `false` | Sends a copy to the submitted address. Enabling this increases email-reflection risk; keep rate limiting and provider limits active. |
| `siteName` | placeholder | Used in mail subjects and copy. |
| `siteUrl` | placeholder | Preferred fixed site origin for redirects. |
| `httpHost` | placeholder | Fixed fallback host when `siteUrl` is blank. Request host headers are never trusted. |
| `contactPage` | `contact.html` | Contact-page path relative to the site origin. |
| `thankYouPage` | `thankyou.html` | Thank-you path relative to the site origin. |
| `timezone` | `UTC` | Timestamp timezone used in mail. |

Admin messages use the visitor's validated email address as `Reply-To`, so replying in a mail client reaches the visitor. The sender remains the configured on-domain address for SPF/DMARC alignment.

### Signed challenge

| Setting | Default | Purpose |
|---|---:|---|
| `formSecret` | placeholder | Required server-only HMAC key of at least 32 characters. Use a different random value on each independent installation. |
| `challengeTtlSec` | `7200` | Maximum age of a challenge. Long forms may need a larger value. |
| `timeTrapEnabled` | `true` | Rejects submissions that arrive too soon after the server issued the challenge. |
| `timeTrapMinMs` | `2000` | Minimum challenge-to-submit interval. |
| `timeTrapGraceMs` | `100` | Small allowance for scheduling and transport variation. |
| `mathChallenge` | `true` | Requires the answer to the server-selected arithmetic question. |

The browser timestamp and public `formKey` used by 0.95 have been removed. In 0.96, PHP chooses the question and timestamp, signs them with `formSecret`, and verifies the signature on POST. A visitor can obtain a challenge—as required for any public form—but cannot alter its signed fields.

This token is an anti-tampering and anti-automation measure, not authentication or a claim of complete CSRF protection. The form is deliberately public and performs no action on an authenticated user account.

### Rate limiting and privacy

| Setting | Default | Purpose |
|---|---:|---|
| `rateLimitEnabled` | `true` | Enables fixed-window limiting based on `REMOTE_ADDR`. |
| `rateLimitMaxAttempts` | `5` | Maximum accepted POST attempts in one window. Validation failures count. Honeypot hits do not. |
| `rateLimitWindowSec` | `600` | Window duration in seconds. |
| `rateLimitStorageDir` | system temp | Optional non-public writable directory for limiter state. |
| `includeIpInEmail` | `false` | Includes the raw visitor IP in the admin email only when enabled. |

Limiter filenames are HMACs of the client IP and the server-only secret; file content contains only a window start and counter. No raw IP is written to the limiter directory. PHP sees the raw IP long enough to calculate the keyed hash. Stale counters are removed opportunistically, and many hosts also clean their system temporary directory automatically.

Gjallarform intentionally uses `REMOTE_ADDR`, not an untrusted forwarding header. If the site is behind a reverse proxy or CDN, all visitors may appear under the proxy address. Configure rate limiting at the proxy, or add provider-specific trusted-proxy handling only after reviewing that provider's documentation.

The limiter fails closed when its storage cannot be created, locked, or written. This avoids silently losing the primary protection against mail flooding; the PHP error log records the storage problem.

## How a submission works

1. The page loads `gjallarform.js`.
2. JavaScript requests a no-cache challenge from `gjallarform.php?gjf_challenge=1`.
3. PHP returns a signed timestamp, nonce, and server-selected question index.
4. JavaScript displays the question and enables Submit.
5. On POST, PHP checks the honeypot, challenge signature and age, timing, answer, and rate limit.
6. PHP validates lengths and the email address, then sends the admin message.
7. If enabled, a best-effort confirmation copy is sent to the visitor.
8. PHP responds with a 303 redirect to the thank-you page.

The arithmetic question adds friction to simple automation. It does not stop a person or a bot capable of reading the page and solving basic arithmetic.

## Template integration

Required user-facing fields are `fullname` (or `name`), `email`, and `message`. `subject` is optional at the PHP layer. The template also contains:

| Field | Set by | Purpose |
|---|---|---|
| `challenge_token` | JavaScript | Signed server challenge submitted back unchanged. |
| `math_answer` | Visitor | Answer to the displayed question when enabled. |
| `website` | Nobody | Accessible-hidden honeypot; must remain empty. |

Do not restore the old `form_key`, `render_ts`, or `math_index` fields. They are not used by 0.96.

`gjallarform.js` attaches its submit behavior only to `form.gjallarform`; unrelated forms on the same page are not intercepted.

## Errors

Validation failures redirect to the configured contact page with an `err` query parameter. The current codes are:

- `challenge_invalid`, `challenge_expired`, `too_fast`
- `math_wrong`, `rate_limited`
- `name_missing`, `email_missing`, `message_missing`
- `name_too_long`, `email_too_long`, `subject_too_long`, `message_too_long`
- `email_ascii_only`, `email_invalid`
- `send_failed`

The script restores a recent draft from `sessionStorage` after a redirect. It never treats browser validation as authoritative; PHP performs the server-side checks.

## Deployment tests

Use a staging copy or one low-risk live installation first.

1. Keep `form_disabled=true`; confirm the challenge request and direct POST return 503.
2. Configure a unique `formSecret`, addresses, URL, and paths; then enable the form.
3. Load the form and confirm Submit becomes enabled and the question appears.
4. Submit valid data; confirm one admin email arrives and Reply reaches the visitor address.
5. Fill the visually hidden `website` field with developer tools; confirm no email is sent.
6. Submit immediately after reloading; confirm the timing rejection.
7. Alter one character in `challenge_token`; confirm rejection.
8. Enter a wrong arithmetic answer; confirm rejection and draft recovery.
9. Reach the configured attempt limit; confirm further attempts are blocked until the window resets.
10. Confirm the rate-limit directory is outside the public web root and writable only by the PHP user.
11. If confirmation copies are needed, enable them and repeat abuse and provider-quota tests before production use.
12. Verify the privacy notice describes the site's actual mail retention, processors, logging, and optional IP-email setting.

Because production mail behavior depends on the host, the repository's automated checks cannot replace these tests.

## Upgrading from 0.95

0.96 intentionally changes the anti-abuse protocol:

1. Replace all four deployable files together; do not mix 0.95 HTML/JavaScript with 0.96 PHP.
2. Remove `formKey` and add a new server-only `formSecret`.
3. Rename `math_challenge` to `mathChallenge`.
4. Add the rate-limit settings and ensure the selected directory is writable and non-public.
5. Decide whether to enable `includeIpInEmail`; it now defaults to false.
6. Decide whether confirmation copies are justified; `sendConfirmation` defaults to false.
7. Update any custom contact markup to use `challenge_token` and the accessible honeypot structure from the template.
8. Repeat the complete deployment test list on every site.

## Security boundaries

Gjallarform helps with common contact-form problems:

- forged or modified timing/question values
- basic automated submissions
- repeated submissions from one directly connected IP
- oversized fields and malformed email addresses
- mail-header injection through submitted subject/configured display values
- accidental host-header use in redirect URLs

It does not by itself stop:

- distributed botnets or denial-of-service traffic
- automation that fetches challenges and solves simple arithmetic
- abuse from many addresses behind a shared proxy
- compromise or misconfiguration of the web or mail server
- delivery problems caused by missing DNS authentication or poor sender reputation

Use HTTPS, current PHP security releases, hosting-level request controls, outbound-mail quotas, monitoring, and backups. Report suspected vulnerabilities privately through GitHub's security-reporting interface when available; otherwise contact the maintainer without publishing exploit details first.

## Privacy notes

Gjallarform is software, not a guarantee of legal compliance. The operator determines purposes, retention, lawful basis, processors, notices, and responses to data-subject requests.

Data can exist in several places:

- submitted contact details and message content in mailboxes and mail-server logs
- optional visitor IP in the admin email
- transient raw IP in PHP memory and keyed-hash limiter files
- name, subject, and draft content in the visitor's `sessionStorage`
- hosting, proxy, CDN, or analytics logs outside Gjallarform

Adapt the example privacy sentence and link it to the site's real privacy notice. Do not claim that the form has no persistent storage merely because it has no database: email and infrastructure logs are storage.

## Development

Run the local checks when PHP and Node.js are installed:

```sh
php -l gjallarform/gjallarform.php
php tests/security_helpers_test.php
node --check gjallarform/gjallarform.js
```

GitHub Actions runs the PHP checks on PHP 8.2, 8.3, 8.4, and 8.5. Publishing a GitHub release builds a deployable ZIP and attaches its SHA-256 checksum.

## License and support

Gjallarform is licensed under GPL-3.0-or-later. See [LICENSE](LICENSE).

- Bugs: [GitHub Issues](https://github.com/Ainz/gjallarform/issues)
- Questions and deployment experiences: [GitHub Discussions](https://github.com/Ainz/gjallarform/discussions)
- Version history: [CHANGELOG.md](CHANGELOG.md)

The project is maintained as a personal open-source project. Test beta releases in your own environment before deploying them broadly.
