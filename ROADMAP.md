# ERASED CMS Roadmap

This is the official implementation checklist for ERASED CMS. For what shipped in the current release,
see [CHANGELOG.md](CHANGELOG.md) — this file tracks what's ahead.

## Current position

Current version is `0.9.3-beta` — **Release Candidate**. Remaining work before `v1.0` is the
stabilization checklist below; no new feature work is planned before then.

---

## Status legend

- [x] Implemented, tested, and pushed
- [ ] Planned or in progress

## Branch strategy

Development happens directly on `main` — no permanent per-version branches.

---

# v1.0 — Stable Release

- [ ] Stable Package, Website Type, Theme, Plugin, Language Pack, and Homepage Section APIs.
- [ ] Production security review.
- [ ] Production documentation.
- [ ] First stable ERASED CMS release.

---

# Commercial direction

ERASED CMS follows an open-core model:

- Community Edition remains useful, secure, self-hosted, and exportable.
- Core security fixes and ownership of user data are never paywalled.
- Professional, Agency, Enterprise, and paid modules may add advanced operational capabilities.
- Commercial functionality must use the same Package Engine as community extensions.

See [`docs/COMMERCIAL-MODEL.md`](docs/COMMERCIAL-MODEL.md).

---

# Completion rule

A task is checked only after:

1. the implementation exists on the correct branch;
2. PHP syntax checks pass;
3. its dedicated smoke or integration test passes;
4. rollback behavior is tested where relevant;
5. the change is pushed to GitHub.
