# ERASED CMS Platform Foundation

This document describes the platform services that connect packages, website profiles, themes,
Homepage Studio, and language packs together. All five are implemented and in active use — this is a
reference for how they work, not a plan for building them.

## Overview

| Service | Namespace | Purpose |
|---|---|---|
| Capability Registry / Resolver | `Erased\Capabilities` | Picks which installed package provides a named capability |
| Package Event System | `Erased\Events` | Lets packages react to platform events without calling each other directly |
| Service Container | `Erased\Container` | Resolves package-declared services by id, with dependency and circularity checks |
| Homepage Block Registry | `Erased\Homepage` | Lets packages register homepage sections Homepage Studio can place |
| Developer Mode | `routes/admin.php` (`/admin/developer`) | Read-only inspectors over all of the above |

Each of the first four follows the same two-class shape: a plain, storage-agnostic class that holds the
resolved state (`CapabilityRegistry`/`CapabilityResolver`, `EventDispatcher`, `ServiceContainer`,
`BlockDefinitionRegistry`), and an `Installed*Runtime` class that rebuilds it from
`InstalledPackageRepository` — reading every installed package's manifest and wiring in only the enabled
ones. `refresh()` on the runtime class re-derives everything from the database; nothing here is cached
across requests.

## 1. Capability Registry and Resolver

`app/Capabilities/CapabilityRegistry.php`, `CapabilityResolver.php`, `InstalledCapabilityRuntime.php`.

A capability is just a string a package's `package.json` declares it `"provides"` and another package's
manifest declares it `"requires"` (e.g. a payments-processing capability, or a search-index capability) —
this is how packages depend on *behavior* rather than on each other by name.

- `CapabilityRegistry` maps capability name → the set of installed packages that provide it, and can
  report a package's missing requirements against what's registered.
- `CapabilityResolver` narrows that further to *enabled* packages only, and picks exactly one: an
  administrator-set preferred provider if one exists, the sole provider if there's only one, or a thrown
  `RuntimeException` if the choice is ambiguous — resolution never guesses.
- `InstalledCapabilityRuntime` is the bridge: it loads every installed package's manifest from
  `InstalledPackageRepository`, registers it, and builds a resolver scoped to the currently-enabled set.

## 2. Package Event System

`app/Events/EventDispatcher.php`, `PackageEvent.php`.

A minimal synchronous dispatcher: `listen(eventName, callback, priority)` registers a listener,
`dispatch(eventName, $event)` runs every matching listener in priority order (ties broken by registration
order) and returns their results. A listener that throws aborts dispatch with a wrapped
`RuntimeException` naming the failing event — failures are visible, not swallowed.

Core dispatches package lifecycle events (installed, updated, enabled, disabled, uninstalled, and
capability-provider changes); packages are free to dispatch and listen for their own application-level
events the same way, without a hardcoded list to extend.

## 3. Service Container

`app/Container/ServiceContainer.php`, `InstalledServiceRuntime.php`, `PackageServiceRegistrar.php`.

A small dependency container, deliberately not a framework: `set(id, factory, shared)` registers a lazy
factory, `get(id)` resolves it (once, if shared), `alias()` lets one id resolve to another, and both
circular factory dependencies and circular aliases are detected and rejected rather than looping forever.

Packages don't call `set()` directly — they declare `services` in their manifest (id, file, class,
shared), and `PackageServiceRegistrar` turns that into container registrations at runtime, validating that
the file path stays inside the package directory and the class name is well-formed before ever
`require_once`-ing it. `InstalledServiceRuntime` runs this for every enabled package on `refresh()`,
rejecting two packages that declare the same service id and tracking which package owns which service.

## 4. Homepage Block Registry

`app/Homepage/BlockDefinition.php`, `BlockDefinitionRegistry.php`, `InstalledBlockRuntime.php`, plus
`BlockPlacement.php`/`HomepagePlacementRepository.php`/`PublishedHomepage*.php` for the placement and
rendering side.

A `BlockDefinition` is a homepage section a package can offer — id, owning package, title/description,
the service that renders it, and any capabilities it requires to be available. `BlockDefinitionRegistry`
holds the full set (including the built-in sections) and can filter it down to only what's currently
available given a `CapabilityResolver`. `InstalledBlockRuntime` registers every enabled package's declared
`homepage_blocks` (e.g. `erased.commerce`'s Featured Products block) the same way `InstalledServiceRuntime`
registers services — one package's bad declaration is recorded as an error and skipped, not fatal to
every other package's blocks.

Hiding an unavailable block from pickers never deletes its saved placement data — disable the providing
package and re-enable it later, and existing layouts still have it.

## 5. Developer Mode

`/admin/developer`, gated by the `developer_mode_enabled` setting (off by default) and the
`security.manage` permission.

Read-only inspectors over everything above: installed and enabled packages, package dependencies and
their resolution status, registered capabilities and which package resolves each one, registered
services, Homepage Studio blocks, and the active Website Profile. Nothing on the page can change state —
it exists to make the platform's runtime wiring inspectable, not to administer it.

## Design constraints

- Every "installed/enabled" state is re-derived from `InstalledPackageRepository` on `refresh()` — no
  service here owns its own copy of what's installed.
- Ambiguity is a hard error everywhere (capability resolution, service id collisions, homepage block id
  collisions) — the platform never silently picks a winner between two packages.
- A misbehaving package degrades gracefully where it can (a bad homepage block declaration is skipped and
  recorded as an error) and fails loudly where correctness depends on it (a bad service declaration or a
  capability that can't resolve throws).
