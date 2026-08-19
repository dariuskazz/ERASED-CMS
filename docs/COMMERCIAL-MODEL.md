# ERASED CMS Commercial Model

ERASED CMS uses an **open-core** model.

## Community Edition

The Community Edition is open source and must remain useful for real websites.

Included:

- Core CMS
- Posts and pages
- Media library
- Basic users and permissions
- Website Types foundation
- Homepage Studio basic layouts
- Theme support
- Language packs
- Package installation
- Security updates
- Manual backups and exports
- Developer documentation
- Self-hosting without telemetry or forced cloud services

The Community Edition must not intentionally weaken security, data ownership, portability, or upgrade safety.

## Professional / Business Edition

Paid editions may add capabilities aimed at companies, agencies, and organizations with operational requirements.

Possible paid features:

- Advanced workflow and approvals
- Multi-site management
- Staging and deployment tools
- Advanced audit logs
- SSO and directory integrations
- Advanced role and permission policies
- Scheduled backups and remote backup targets
- Advanced analytics and reporting
- SLA and priority support
- Compliance tools
- White-label administration
- Team collaboration features
- Advanced package update channels
- Central fleet management
- Enterprise migration services

## Paid Modules

Commercial functionality can also be sold as independent packages.

Examples:

- ERASED Commerce Pro
- Booking
- CRM
- Memberships
- Advanced Analytics
- Enterprise Search
- Backup Pro
- Deployment Manager
- Agency Toolkit

A Blog, News, Business, Gallery, or Community website can install paid modules later without reinstalling ERASED CMS.

## Licensing principles

- The open-source core remains usable without payment.
- Security fixes are never restricted to paid customers.
- User content and configuration remain exportable.
- Paid licenses unlock packages or services, not ownership of customer data.
- Disabling or allowing a license to expire must not delete content.
- Paid modules should degrade gracefully to a safe read-only or disabled state where appropriate.
- License validation should support offline/self-hosted environments when possible.
- No hidden telemetry.

## Distribution options

Potential product structure:

1. **ERASED CMS Community** — open source.
2. **ERASED CMS Professional** — paid package bundle for businesses.
3. **ERASED CMS Agency** — multi-site and client-management tools.
4. **ERASED CMS Enterprise** — SSO, compliance, audit, deployment, and support.
5. **Individual paid modules** — Commerce Pro, Backup Pro, Analytics Pro, and others.

## Architecture rule

Commercial features must use the same Package Engine as open-source extensions. The core must not contain hidden paid-only branches that make maintenance unsafe or prevent community contributors from understanding the architecture.

## Goal

The commercial model should fund development while preserving the project values:

> Simple for users. Powerful for creators. Built to last.
