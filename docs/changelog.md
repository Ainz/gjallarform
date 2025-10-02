# Changelog

All notable changes to the Conram Contact Form Engine will be documented here.  
This project follows [semantic versioning](https://semver.org/) (MAJOR.MINOR.PATCH).

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
