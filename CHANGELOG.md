# Changelog

All notable changes to Relmin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## ## [0.99.2] - 2026-01-21

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
- Configurable page filenames via `$CFG['contactPage']` and `$CFG['thankYouPage']`
- Support for custom filenames and subdirectory paths (e.g., `forms/contact.html`)
- Comprehensive inline documentation (PHPDoc and JSDoc) for all functions
- Detailed explanatory comments throughout codebase for improved maintainability

### Fixed
- File encoding issue with Unicode box-drawing characters in comments replaced with ASCII hyphens

### Changed
- **Versioning scheme simplified** from 0.99.x to 0.9x series (0.99.1 → 0.91, future 0.99.2 → 0.92, etc.)
- This change makes version numbers easier to read and maintain while maintaining semantic versioning principles

## [0.91] - 2026-01-15
(Previously released as 0.99.1)

### Security
- **CRITICAL**: Implemented timing-attack-safe form key comparison using `hash_equals()` instead of string comparison operator
- **HIGH**: Prevented host header injection vulnerability by requiring explicit configuration and never trusting `$_SERVER['HTTP_HOST']`
- **HIGH**: Added subject line sanitization to prevent email header injection attacks via newline characters
- **MEDIUM**: Replaced hardcoded credentials with template placeholder values to prevent exposure in public repositories

### Fixed
- Removed duplicate `render_ts` POST variable read in validation logic
- Updated outdated CSS class references in documentation (`.cf` → `.relmin`)

### Changed
- License headers updated from `Proprietary` to `GPL-3.0-or-later` in source files to match LICENSE file and README

## [0.90-RC] - 2025-11-15
(Previously released as 0.99-RC)

### About This Release
Release Candidate for Relmin 1.0. Core functionality is stable and tested. Suitable for production use on personal and small business sites. Public release pending final documentation review.

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
- Single form per page (form-agnostic support planned for 1.x)
- No rate limiting at application level (relies on infrastructure)
- No database/file logging (mail-only audit trail)
- No AJAX submission mode
- sessionStorage only (no cross-tab draft sharing)

### File Structure
```
relmin/
├── contact.php          # Backend handler
├── relmin.js            # Frontend UI layer
├── contact.html         # Example form markup
├── thankyou.html        # Example thank-you page
├── README.md            # Documentation
├── CHANGELOG.md         # This file
└── LICENSE              # License terms
```

### Configuration
All user configuration is centralized in the `$CFG` array at the top of `contact.php`. No code changes required below the config block.

### Upgrade Notes
This is the first public release candidate. No upgrade path exists from earlier private versions.

---

## [Unreleased]

### Planned for 1.0
- Finalized public documentation
- Installation video/guide
- Community feedback integration

### Under Consideration for 1.x
- Form-agnostic pattern matching (work with any form structure)
- Optional rate limiting module (strict tier)
- Database logging option
- Multi-form support on single page
- AJAX submission mode
- Localization/i18n support

