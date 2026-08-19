# Changelog

All notable changes to ERASED CMS are documented here. Development happens directly on `main` — see
[ROADMAP.md](ROADMAP.md) for what's planned ahead.

## `0.9.3-beta` — Initial public release

First public release. Highlights:

- A modular Package Engine — install, update, roll back, and uninstall ZIP packages with dependency
  resolution, capabilities, events, and a service container.
- Website types, homepage presets, and website profiles, edited through ERASED Studio's unified
  Layout/Navigation/Theme/Typography/Media tabs.
- A Theme Engine with installable admin and website themes.
- A Language Pack system (translation base generation, ZIP export, install/enable/disable).
- `erased.commerce` (product catalog, storefront, cart, checkout, coupons, category tree, stats) and
  `erased.payments` as real installed packages, not core features.
- Users, roles, and permissions; authentication with 2FA, session protection, and a Security Center.
- Database and media backup/restore, with automatic retention and integrity-checked restores.
- An accessibility- and performance-audited, mobile-adapted admin panel across multiple visual themes.
- Core self-update from an uploaded release ZIP — staged review before anything applies, automatic
  rollback on failure.

---

Future releases will be logged here going forward.
