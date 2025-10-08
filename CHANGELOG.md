# Changelog


All notable changes to this project will be documented here.  
This file follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) style.

## [Unreleased]
- Planned improvements: client-side error mapping, optional live counters, optional logging, optional PHPMailer support.
---
## [1.2.0] – 2025-10-08
### Changed
- Front-end assets consolidated into a single hosted file:
  `https://service.conram.it/contactulus/contactulus.js`
- Form and Thank-you pages now include one `<script>`; CSS is injected automatically.

### Deprecated
- `frontend/assets/js/contact-thanks.js` – replaced by the hosted script.
  Will be removed in the next release.

### Fixed
- Wider, more resilient 2-column grid; clearer spacing.
- F5/back-forward cache: form no longer repopulates on plain reload;
  drafts only restore on `?err=…` bounce.

### Docs
- Added `docs/MIGRATION-1.2.md` with upgrade steps.

---
## Name change to Contactulus
### Why “Contactulus”?

From Latin "contactus" (connection, touch) and the diminutive suffix -ulus, Contactulus means “a little contact.”
It reflects the project’s goal: a small, efficient contact form that keeps communication simple and self-contained.

---
## Conram Contact Form Engine — v1.1.4 - 7 october 2025
### Highlights
- Added client-side error management with friendly user feedback.
- Introduced `contact.php` redirect handling (`thank-you.html` and `?err=` return paths).
- Added submitter confirmation emails with reference IDs.
- Integrated draft cache (prevents message loss during validation errors).
- Refined input validation and honeypot logic.

### Maintenance
- Codebase synchronized across GitLab and GitHub.
- Documentation refined (`FORM_FLOW.md`, `PREREQUISITES.md`, `CHANGELOG.md`).
- Dual-license model clarified (GPLv3 + commercial option).

### Tested
- Verified mail delivery and auto-confirmation on PHP 8.2 shared hosting.
- Verified front-end operation on Publii static sites.
---
## [1.1.3] – 2025-10-02
### Added
- Automatic confirmation email sent to submitter upon successful delivery.
  - Uses aligned From (`form-engine@conram.it`) for SPF/DMARC compliance.
  - Includes polite acknowledgement, original subject, and reference ID.
  - Adds headers (`Auto-Submitted`, `Precedence`, `X-Auto-Response-Suppress`) to prevent auto-reply loops.
- Short **Reference ID** now generated server-side.
  - Included in both admin and submitter mails.
  - Exposed in JSON response (`ref`) for potential AJAX usage.

### Changed
- Admin notification subject line now includes `[REF]` for easier tracking.
- Admin message body starts with `Reference: ...` for consistent traceability.

---
## [1.1.2] – 2025-10-02
### Added
- Implemented **thank-you page redirect** in `contact.php` using the PRG pattern (303 redirect after POST).
- Introduced **dual-mode responses**:
  - **AJAX clients** receive JSON (`{ ok:true }`).
  - **Normal browser form posts** redirect to `thank-you.html`.
- Added **site_tag → thank-you URL whitelist mapping** to avoid open redirects.
- Integrated **client-side ephemeral storage** (`sessionStorage`) to personalize the thank-you page with first name, subject, and a short reference ID.
- Created `contact-thanks.js` helper for safe handling of thank-you data in Publii (linked via Theme Footer).

### Changed
- Moved `Content-Type: application/json` header logic to be conditional (only set when JSON is actually returned).
- Honeypot branch now silently redirects to `thank-you.html` for normal form posts, instead of returning JSON only.

### Notes
- Thank-you personalization is **ephemeral**: no server-side storage. Data is cleared from the browser after rendering.
- Page slug for thank-you is `thank-you.html` (adjust mapping if changed in the future).

---
## [2025-09-30] Initial release
- First public version of the Contact Form Engine.
- Backend (`contact.php`) with validation, rate limiting, spam protection.
- Debug endpoint (`contact-echo.php`) for testing integration.
- Frontend snippet (`contact-form.html`) ready for static html capable sites or platforms.
- Documentation: prerequisites, flow, and usage notes.

---
## [1.1.0] - 2025-10-15
### Changed
- Planned JS live counters for fields with `data-count`.
- Improved phone validation regex to support international formats.


## [1.0.0] - 2025-09-30
### Added
- Initial release of the Conram Contact Form Engine.
- Backend `contact.php` with:
  - CORS restrictions (`conram.it` only).
  - Honeypot, CSRF token, and render-time trap.
  - Rate limiting (10 posts / 30 min / IP).
  - Validation:
    - Name & Subject: extended Latin allowed (å/ä/ö/ø/æ/é), emojis blocked.
    - Email: validated with `FILTER_VALIDATE_EMAIL`.
    - Phone: optional, allows digits + `+ - ( ) /`, 6–32 chars.
    - Message: max 4000 chars, control chars stripped, emojis blocked.
- Debug endpoint `contact-echo.php` for integration testing.
- Frontend form snippet (`frontend/contact-form.html`) with:
  - Responsive grid layout.
  - Titles indicating max characters.
  - Placeholders for usability.
- Documentation:
  - `README.md` (overview, usage, roadmap).
  - `CHANGELOG.md` (this file).

---

## Planned
- Friendly client-side error message mapping.
- Optional live character counters via external JS.
- Message logging to file or database (for archival/audit).







