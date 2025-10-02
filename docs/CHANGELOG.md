# Changelog

# Changelog

All notable changes to this project will be documented here.  
This file follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) style.

## [Unreleased]
- Planned improvements: client-side error mapping, optional live counters, optional logging, optional PHPMailer support.

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
