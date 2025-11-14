# Changelog

All notable changes to Contactulus will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.99-RC] - 2025-11-15

### About This Release
Release Candidate for Contactulus 1.0. Core functionality is stable and tested. Suitable for production use on personal and small business sites. Public release pending final documentation review.

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
contactulus/
├── contact.php          # Backend handler
├── contactulus.js       # Frontend UI layer
├── contact-form.html    # Example form markup
├── thank-you.html       # Example thank-you page
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
