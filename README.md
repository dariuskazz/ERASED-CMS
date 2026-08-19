# ERASED CMS

**A modular, self-hosted PHP content platform that grows with your website.**

ERASED CMS is built around a small stable core, one shared Package Engine, capability-based extensions, upgrade-safe operations, and user-owned data.

> **Current version:** `0.9.3-beta` — **Release Candidate**  
> **Release status:** pre-release; final hardening and documentation are the remaining work before `v1.0`

## Development focus

Development happens directly on `main` — there are no permanent per-version branches. What's planned ahead is tracked in [ROADMAP.md](ROADMAP.md); what's shipped is tracked in [CHANGELOG.md](CHANGELOG.md).

The remaining work before `v1.0` is stabilization rather than new features: upgrade-path testing, completing user and developer documentation, and a final accessibility/performance/release-candidate testing pass. See [ROADMAP.md](ROADMAP.md) for the full checklist.

## Vision

One ERASED CMS installation should be able to start as a blog, news portal, business site, portfolio, gallery, video site, or shop — and gain new capabilities later without reinstalling or losing content.

Core principles:

- small, stable core;
- everything extensible uses packages;
- capabilities instead of hardcoded providers;
- privacy-first and self-hosted-first;
- backups and rollback before destructive operations;
- no vendor lock-in;
- AI is optional and the administrator remains in control;
- shared-hosting compatibility without requiring Composer or Node.js on the server.

Read the [Manifesto](docs/MANIFESTO.md) and [Design Principles](docs/DESIGN-PRINCIPLES.md).

## Current capabilities

- content and page management, with revisions, scheduling, access rules, and SEO fields;
- media library with automatic thumbnail generation and a photo gallery system;
- a modular Package Engine — install, update, roll back, and uninstall ZIP packages with dependency resolution, capabilities, events, and a service container;
- website types, homepage presets, and website profiles, edited through ERASED Studio's unified Layout/Navigation/Theme/Typography/Media tabs;
- a Theme Engine — 4 built-in admin themes, 3 built-in and 5 installable website themes, plus ZIP-installable custom themes for both;
- a Language Pack system (translation base generation, ZIP export, install/enable/disable);
- `erased.commerce` (product catalog, storefront, cart, checkout, coupons) and `erased.payments` as real installed packages, not core features;
- users, roles, and permissions; authentication with 2FA, session protection, and a Security Center;
- database and media backup/restore, with automatic retention and integrity-checked restores;
- an accessibility- and performance-audited, mobile-adapted admin panel across four selectable visual themes;
- core self-update from an uploaded release ZIP — staged review before anything applies, automatic rollback on failure.

## Requirements

- PHP 8.3+
- MariaDB or MySQL
- Apache
- PHP extensions: `pdo_mysql`, `zip`
- PHP extension `gd` (recommended) — enables automatic media thumbnail generation; the app runs without it, just without thumbnails

PostgreSQL and SQLite portability are architectural goals, but MariaDB/MySQL is currently the only production-targeted database family.

## Local development

```bash
podman compose build app
podman compose up -d
```

Open:

```text
http://127.0.0.1:8080
```

## Repository guide

- [Roadmap](ROADMAP.md)
- [Changelog](CHANGELOG.md)
- [Documentation index](docs/README.md)
- [Platform Foundation](docs/PLATFORM-FOUNDATION.md)
- [Commercial model](docs/COMMERCIAL-MODEL.md)
- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md)

## Commercial direction

ERASED CMS follows an open-core model. The Community Edition must remain useful, secure, self-hosted, exportable, and complete without mandatory cloud or AI services. Professional, Agency, Enterprise, and paid packages may add advanced operational capabilities through the same public Package Engine.

## License

ERASED CMS is licensed under the [GNU Affero General Public License v3.0](LICENSE). In short: you're free to use, modify, and self-host it, but if you run a modified version as a network service, you must make that version's source available to its users too.

---

**Freedom without complexity.**
