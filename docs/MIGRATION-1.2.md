# Migration Guide — v1.2.0

## Summary
Starting from v1.2.0, all front-end assets are consolidated into a single hosted script:

<script src="https://service.conram.it/contactulus/contactulus.js" defer></script>

This script injects its own CSS, handles validation, drafts, and thank-you pages.
Publii users no longer need to link separate CSS or JS files.

## What Changed
- Old assets (`frontend/assets/js/contact-thanks.js`, CSS) are **deprecated**.
- Form and Thank-you pages only require one `<script>` include.
- Layout, validation, and thank-you logic are now unified in `contactulus.js`.
- Improved layout grid and F5/back-forward behavior.

## Upgrade Steps
1. Remove any `<link>` to old form CSS or JS in your site.
2. Add this single line before the closing `</body>`:

<script src="https://service.conram.it/contactulus/contactulus.js" defer></script>

3. Keep your existing form HTML (no structural changes required).
4. Optionally remove deprecated local JS/CSS files.

## Backward Compatibility
The old files still exist as stubs for one release cycle.
They will be removed in the next version.
