# Contributing to ERASED CMS

Thank you for helping improve ERASED CMS.

## Development branch

- `main` — all active development happens directly here; no permanent per-version branches. Milestones are tracked by roadmap section in [ROADMAP.md](ROADMAP.md), not by branch. Current version: `0.9.3-beta` (`v0.9-dev` — Release Candidate).

Open feature work against `main` unless a maintainer asks otherwise.

## Before coding

Read:

- `docs/MANIFESTO.md`
- `docs/DESIGN-PRINCIPLES.md`
- `docs/PLATFORM-FOUNDATION.md`
- `ROADMAP.md`

New features should use the Package Engine and capability model rather than adding hardcoded core dependencies.

## Code standards

- PHP 8.3+
- `declare(strict_types=1);` in new PHP files
- PSR-12 style where practical
- typed parameters and return values where practical
- no business logic in templates
- no runtime database schema changes
- no silent exception swallowing in new platform code
- no credentials, generated backups, local storage, or environment-specific configuration in commits

## Validation

Before submitting a change:

1. Run `php -l` on changed PHP files.
2. Run the relevant tool under `tools/test-*.php`.
3. Test rollback or failure behavior for lifecycle, migration, install, update, or removal changes.
4. Confirm unrelated local changes are not included.
5. Update documentation and roadmap status only after tests pass.

## Commit messages

Use descriptive subsystem-oriented messages, for example:

```text
Package Engine: Add update rollback
Capability Registry: Resolve enabled providers
Docs: Add shared-hosting deployment guide
```

## Pull requests

Describe:

- the problem being solved;
- the architectural approach;
- tests performed;
- migration or rollback impact;
- security implications;
- documentation updated.

Keep pull requests focused. Unrelated cleanup should be submitted separately.
