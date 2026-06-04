# Changelog

All notable changes to Gjallarform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Public Beta Release]

## [0.95] - 2026-06-04

### About This Release
First public beta release. The form has been running on two live sites — [conram.it](https://www.conram.it) and [rikardmalmborg.se](https://www.rikardmalmborg.se) — and tested against real-world spam conditions. Treat as beta software: review the code and test in your own environment before deploying.

### Added
- Prominent `[!WARNING]` callout at the top of the Configuration Reference section in README noting that `form_disabled` defaults to `true` and must be set to `false` before the form will accept live submissions
- `form_disabled` documented in the Configuration Reference table
- `form_disabled` added as the first item in the Deployment Checklist
- Client-side email format validation added to `attachEmailAsciiGuard` — catches malformed addresses (e.g. missing domain dot) that the browser's native `type=email` passes silently; mirrors the server-side PHP regex
- File headers added to all four files (copyright, version, license, GitHub link)

### Changed
- PHP version requirement standardized to 8.1+ throughout README (was inconsistently 8.1 in one place, 8.2 in another)
- `formKey` configuration examples in README and `gjallarform.php` replaced with a fixed placeholder string; previous examples used `rand()` which generated a new value on every page load, misrepresenting the requirement that `formKey` must be a fixed secret matching the hidden HTML field
- Math question pool expanded from 7 to 15 questions
- Math question seeding changed from CRC32(formKey) to MD5(formKey + HTTP_HOST) — ensures different questions appear on different domains even with identical formKeys
- All four files consolidated into `gjallarform/` directory — self-contained deployable package
- Field order in `contact.html` template corrected to Name → Email → Subject → Message → Math challenge

### Removed
- User-facing reference number removed from thank-you page — the sessionStorage ref was not guaranteed to match the server-generated `$ref`, making it misleading; the authoritative reference now appears in admin and confirmation emails only

## [0.94] - 2026-04-21

### Security
- **HIGH**: Sanitize `$CFG['siteName']`, `$CFG['fromDisplay']`, and `$CFG['replyDisplay']`
  against CRLF header injection at runtime — these values are now stripped of `\r\n`
  before any use in email headers, matching the existing treatment of `$subject`
- **MEDIUM**: Math challenge index is now computed server-side from `formKey` using
  `get_math_question_index()`, preventing a user from choosing an arbitrary question by
  posting a custom `math_index`; the client-reported index is only used as a fallback
  when `formKey` is intentionally left blank
- **MEDIUM**: Reference IDs now generated via `random_bytes(3)` (CSPRNG) instead of
  `md5(uniqid('', true))` (microtime-seeded, predictable on many platforms)
- **LOW**: Added server-side field length limits: name ≤ 200, email ≤ 254 (RFC 5321),
  subject ≤ 300, message ≤ 10 000 bytes; matching `maxlength` attributes added to
  example `contact.html`; corresponding field-level validation bubbles added to JS

### Fixed
- **PHP 8.4 / correctness**: JavaScript `crc32()` was not implementing standard
  IEEE 802.3 CRC32 and produced different values than PHP's `crc32()` for the same
  input; replaced with a correct table-based implementation (polynomial 0xEDB88320)
  and updated `get_math_question_index()` to use `& 0xFFFFFFFF` (unsigned 32-bit mask)
  to match JavaScript's `>>> 0` — PHP and JS now select identical question indices for
  any given `formKey`
- **PHP 8.4 / robustness**: `preg_split()` return value guarded against `false`; a
  `TypeError` on `false[0]` would otherwise be possible under `strict_types=1`
- **PHP 8.4 / robustness**: `new DateTimeZone()` wrapped in `try/catch \Exception`
  — an invalid `$CFG['timezone']` value now silently falls back to `UTC` instead of
  producing a fatal `DateInvalidTimeZoneException` (PHP 8.3+) or `Exception` (PHP 8.0–8.2)

### Changed
- `mt_rand()` replaced with `random_int()` in `get_math_question_index()` for the
  no-formKey fallback path; `random_int()` uses the OS CSPRNG (PHP 7.0+)
- Minimum documented PHP version raised from 8.0 to 8.1 (PHP 8.0 reached EOL
  November 2023; the code runs unchanged on PHP 8.1 through 8.4)

## [0.92] - 2026-01-21

### About This Release
Simplified versioning from 0.99.x to 0.9x series for clarity. This release adds math challenge spam defense as a Standard tier protection feature, along with improved documentation and configuration flexibility.

### Added
- **Math challenge spam defense** - Simple arithmetic questions as Standard tier protection
  - 7 rotating math questions (addition, subtraction, multiplication, division)
  - Deterministic question selection based on form key using CRC32 hash
  - Client-side and server-side validation for math answers
  - Stops manual spam operators while remaining trivial for legitimate users
  - Configurable via `$CFG['math_challenge']` (enabled by default in Standard tier)
  - New error codes: `math_wrong` (incorrect answer), `math_invalid` (invalid format)
  - Draft save/restore includes math answer for seamless error recovery

### Fixed
- Removed hardcoded page filename defaults from URL helper functions (`thank_you_url()` and `back_url()`)
- File encoding issue with Unicode box-drawing characters in comments replaced with ASCII hyphens

### Changed
- **Versioning scheme simplified** from 0.99.x to 0.9x series (0.99.1 → 0.91, future 0.99.2 → 0.92, etc.)
- This change makes version numbers easier to read and maintain while maintaining semantic versioning principles

## [0.91] - 2026-01-15
(Previously released as 0.99.1)

### Added
- Configurable page filenames via `$CFG['contactPage']` and `$CFG['thankYouPage']`
- Support for custom filenames and subdirectory paths (e.g., `forms/contact.html`)
- Comprehensive inline documentation (PHPDoc and JSDoc) for all functions
- Detailed explanatory comments throughout codebase for improved maintainability

### Security
- **CRITICAL**: Implemented timing-attack-safe form key comparison using `hash_equals()` instead of string comparison operator
- **HIGH**: Prevented host header injection vulnerability by requiring explicit configuration and never trusting `$_SERVER['HTTP_HOST']`
- **HIGH**: Added subject line sanitization to prevent email header injection attacks via newline characters
- **MEDIUM**: Replaced hardcoded credentials with template placeholder values to prevent exposure in public repositories

### Fixed
- Removed duplicate `render_ts` POST variable read in validation logic
- Updated outdated CSS class references in documentation (`.cf` → `.gjallarform`)

### Changed
- License headers updated from `Proprietary` to `GPL-3.0-or-later` in source files to match LICENSE file and README

## [0.90-RC] - 2025-11-15
(Previously released as 0.99-RC)

### About This Release
First public release. Core functionality stable and tested. Suitable for production use on personal and small business sites.

### Core Features

**Defense System (Tiered)**
- Honeypot field (primary bot defense, always enabled)
- Time trap with graceful degradation (optional, configurable)
- Form key validation (optional CSRF-like protection)
- Tiered defense levels: `basic` (honeypot only), `standard` (+ time trap), `strict` (reserved for future rate limiting)

**Mail Handling**
- PRG pattern (Post-Redirect-Get) with 303 redirects
- Admin notification emails with reference IDs
- Automatic confirmation emails to submitters
- Envelope sender configuration (`-f` flag) for SPF/DKIM alignment
- Best-effort delivery with error logging

**Validation**
- Required field checks (name, email, message)
- ASCII-only email validation with strict pattern matching
- Character limits enforced (configurable via HTML attributes)
- Subject line defaults to site name if not provided

**User Experience**
- Draft recovery via sessionStorage (survives validation errors)
- Native browser validation with custom messages
- Field-level error bubbles (no global banners)
- Thank-you page with optional personalization
- Accessible form structure with proper labels

**Security Hardening**
- Header sanitization (defense against email injection)
- Timing-attack-safe form key comparison (`hash_equals`)
- Error logging without exposing details to users
- IP address logging (disclosed to users)

### Technical Requirements
- PHP 8.0 or higher
- PHP `mail()` function configured and working
- Mail server with DKIM/SPF recommended
- Server-side mail sending limits strongly recommended (e.g., 50/day)

### Known Limitations
- Single form per page (form-agnostic support planned for future)
- No rate limiting at application level (relies on infrastructure)
- No database/file logging (mail-only audit trail)
- No AJAX submission mode
- sessionStorage only (no cross-tab draft sharing)

### Configuration
All user configuration is centralized in the `$CFG` array at the top of `gjallarform.php`. No code changes required below the config block.
